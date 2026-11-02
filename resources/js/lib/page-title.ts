const appName = import.meta.env.VITE_APP_NAME || 'TravelEasy';

/** Browser tab title ("<Page> | TravelEasy"), shared by the client (app.tsx) and server (ssr.tsx) entries. */
export function pageTitle(title: string): string {
    return title ? `${title} | ${appName}` : appName;
}
