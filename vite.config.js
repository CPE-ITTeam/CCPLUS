// vite.config.js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    base: '/',

    server: {
        host: '0.0.0.0', // Listens on all local network interfaces
        port: 5173,
        hmr: {
            host: '192.168.0.202' // Forces Vite to use your real computer IP for assets
        }
    },

    // ─── ADD THIS SPECIFIC BLOCK FOR THE SANDBOX OPTIMIZATION ───
    optimizeDeps: {
        include: ['xlsx'] // Forces Vite to properly pre-bundle the CDN tarball package [2]
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
