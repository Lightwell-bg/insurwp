import react from '@vitejs/plugin-react';
import { loadEnv } from 'vite';
import { defineConfig } from 'vitest/config';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', '');

  return {
    base: '/',
    plugins: [react()],
    server: {
      port: 5173,
      // В разработке запросы идут на собственный адрес дев-сервера и проксируются
      // на сайт: CORS не участвует, и можно работать, не трогая настройки плагина.
      proxy: {
        '/api': {
          target: env.VITE_INSURWP_API_PROXY || 'https://bginfo.eu',
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api/, '/wp-json/insurwp/v1'),
        },
      },
    },
    test: {
      environment: 'node',
      include: ['tests/**/*.test.ts'],
    },
  };
});
