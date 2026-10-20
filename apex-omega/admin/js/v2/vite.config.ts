import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: '../build',
    lib: { entry: 'src/main.tsx', name: 'ApexV2', fileName: 'apex-v2' },
    rollupOptions: { external: ['react','react-dom'] }
  },
  server: { host: '0.0.0.0', port: 5173 }
})
