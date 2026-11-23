import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [tailwindcss()],
    publicDir: false,
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        rollupOptions: {
            input: 'resources/js/app.js',
            output: {
                entryFileNames: 'app.js',
                assetFileNames: 'app.[ext]',
            },
        },
    },
});
