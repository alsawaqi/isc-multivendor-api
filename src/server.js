const app = require('./app');
const config = require('./config/config');

const PORT = config.port;

app.listen(PORT, () => {
  console.log(`🚀 ISC Multivendor API is running on port ${PORT}`);
  console.log(`📝 Environment: ${config.env}`);
  console.log(`🔗 Base URL: http://localhost:${PORT}${config.api.prefix}`);
  console.log(`\n📚 Available endpoints:`);
  console.log(`   GET  ${config.api.prefix}/health - Health check`);
  console.log(`   GET  ${config.api.prefix}/vendors - Get all vendors`);
  console.log(`   POST ${config.api.prefix}/vendors - Create vendor`);
  console.log(`   GET  ${config.api.prefix}/products - Get all products`);
  console.log(`   POST ${config.api.prefix}/products - Create product`);
});
