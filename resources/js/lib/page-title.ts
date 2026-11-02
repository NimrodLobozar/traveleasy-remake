const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/** Browser tab title, shared by the client (app.tsx) and server (ssr.tsx) entries. */
export function pageTitle(title: string): string {
    return title ? `${title} - ${appName}` : appName;
}
