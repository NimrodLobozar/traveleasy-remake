/// <reference types="vite/client" />

// Typed env variables, so `import.meta.env.*` isn't `any` (vite/client allows any key).
interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}
