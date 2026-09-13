import { existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy';

const candidateInputs = [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/style.css',
    'resources/css/aureon-dashboard.css',
    'resources/css/aureon-auth.css',
    'resources/css/aureon-home.css',
    'resources/js/script.js',
    'resources/js/aureon-dashboard.js',
    'resources/js/aureon-auth.js',
    'resources/js/aureon-home.js',
    'app/Modules/Commerce/Resources/css/storefront.css',
    'app/Modules/Commerce/Resources/js/storefront.js',
    'app/Modules/Commerce/Resources/css/admin-dashboard.css',
    'app/Modules/Commerce/Resources/js/admin-dashboard.js',
    'app/Modules/Commerce/Resources/css/pos.css',
    'app/Modules/Commerce/Resources/css/cashier-sales-history.css',
    'app/Modules/Commerce/Resources/js/pos.js',
    'app/Modules/PropertyBooking/Resources/css/admin.css',
    'app/Modules/PropertyBooking/Resources/js/admin.js',
    'app/Modules/PropertyBooking/Resources/css/storefront.css',
    'app/Modules/PropertyBooking/Resources/js/storefront.js',
    'app/Modules/PropertyBooking/Resources/css/pob.css',
    'app/Modules/PropertyBooking/Resources/js/pob.js',
];

const candidateCopyTargets = [
    { src: 'resources/css', dest: '' },
    { src: 'resources/scss', dest: '' },
    { src: 'resources/fonts', dest: '' },
    { src: 'resources/img', dest: '' },
    { src: 'resources/js', dest: '' },
    { src: 'resources/plugins', dest: '' },
    { src: 'resources/aureon/assets', dest: '../aureon' },
];

const inputs = candidateInputs.filter((input) => existsSync(input));
const copyTargets = candidateCopyTargets.filter((target) => existsSync(target.src));

export default defineConfig({
    build: {
        outDir: 'public/build',
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    const assetName = assetInfo.names?.[0] ?? '[name]';

                    return assetName.endsWith('.css')
                        ? 'css/[name]-[hash].min.css'
                        : `icons/${assetName}`;
                },
                entryFileNames: 'js/[name]-[hash].js',
            },
        },
    },
    plugins: [
        laravel({
            input: inputs,
            refresh: true,
        }),
        ...(copyTargets.length > 0
            ? [viteStaticCopy({ targets: copyTargets })]
            : []),
    ],
});
