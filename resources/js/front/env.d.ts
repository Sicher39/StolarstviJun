/// <reference types="vite/client" />

export {};

declare module '*.vue' {
    const component: any;

    export default component;
}

declare module '*.css' {
    const content: string;

    export default content;
}

declare global {
    function route(...args: unknown[]): any;
}
