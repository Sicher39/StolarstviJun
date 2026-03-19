/// <reference types="vite/client" />

export {};

declare module '*.vue' {
    const component: any;

    export default component;
}

declare global {
    function route(...args: unknown[]): any;
}
