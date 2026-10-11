import { connect, createServer } from 'node:net';
import type { Server, Socket } from 'node:net';

/**
 * Starts the TCP forwards from E2E_FORWARD (`<localPort>=<host>:<port>[,…]`) on 127.0.0.1 and
 * returns a teardown that closes them.
 *
 * Why: in dev the page loads its JS from http://localhost:5173 (public/hot) and is served from
 * http://localhost:8000 (APP_URL). Inside a container, `localhost` is the container itself. The
 * e2e container therefore shares the vite container's network namespace (localhost:5173 is the
 * real Vite dev server) and forwards localhost:8000 to the app container. The browser keeps the
 * same origins as on the host, so CORS, asset URLs and the HMR socket behave exactly as in dev,
 * and the tests can't pass on HTML whose JavaScript never loaded.
 */
function parseForwards(
    value: string,
): { localPort: number; host: string; port: number }[] {
    return value
        .split(',')
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '')
        .map((entry) => {
            const match = /^(\d+)=([\w.-]+):(\d+)$/.exec(entry);
            if (
                match?.[1] === undefined ||
                match[2] === undefined ||
                match[3] === undefined
            ) {
                throw new Error(
                    `E2E_FORWARD entry "${entry}" is invalid; expected <localPort>=<host>:<port>.`,
                );
            }

            return {
                localPort: Number(match[1]),
                host: match[2],
                port: Number(match[3]),
            };
        });
}

function listen(server: Server, port: number): Promise<void> {
    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(port, '127.0.0.1', () => {
            resolve();
        });
    });
}

export default async function globalSetup(): Promise<() => Promise<void>> {
    const forwards = parseForwards(process.env.E2E_FORWARD ?? '');
    const sockets = new Set<Socket>();
    const track = (socket: Socket) => {
        sockets.add(socket);
        socket.once('close', () => sockets.delete(socket));
    };
    const servers = forwards.map(({ host, port }) =>
        createServer((client) => {
            const upstream = connect(port, host);
            track(client);
            track(upstream);
            client.pipe(upstream).pipe(client);
            client.on('error', () => upstream.destroy());
            upstream.on('error', () => client.destroy());
        }),
    );

    await Promise.all(
        servers.map((server, index) =>
            listen(server, forwards[index]?.localPort ?? 0),
        ),
    );

    return async () => {
        // Kept-alive connections would otherwise hold close() open.
        for (const socket of sockets) {
            socket.destroy();
        }
        await Promise.all(
            servers.map(
                (server) =>
                    new Promise<void>((resolve) => {
                        server.close(() => {
                            resolve();
                        });
                    }),
            ),
        );
    };
}
