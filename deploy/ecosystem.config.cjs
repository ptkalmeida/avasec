// Configuração do PM2 para manter o servidor Node do AVASEC no ar em produção.
// Uso no VPS (após `npm run build`):
//   pm2 start deploy/ecosystem.config.cjs
//   pm2 save && pm2 startup   (para reiniciar automaticamente com o servidor)
module.exports = {
  apps: [
    {
      name: 'avasec',
      script: 'dist/server.cjs',
      cwd: __dirname + '/..',
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
      },
      max_memory_restart: '300M',
      error_file: 'logs/pm2-error.log',
      out_file: 'logs/pm2-out.log',
      time: true,
    },
  ],
};
