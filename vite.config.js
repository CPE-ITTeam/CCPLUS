// vite.config.js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    base: '/',

        // ─── ADD THIS SPECIFIC SERVER BLOCK HERE ───
    server: {
        host: '0.0.0.0', // Listens on all local network interfaces
        port: 5173,
        hmr: {
            host: '192.168.0.202' // Forces Vite to use your real computer IP for assets
        }
    },

    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
                silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import']
            }
        }
    },
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ 
            autoImport: true,
        }),
        laravel({
           input: ['resources/js/app.js'],
           refresh: true,
        }),
    ],
    build: {
        outDir: 'public',
        emptyOutDir: false,
    },
});
