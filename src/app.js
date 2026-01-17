const express = require('express');
const cors = require('cors');
const config = require('./config/config');
const routes = require('./routes');
const { errorHandler, notFound } = require('./middleware/errorHandler');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// API routes
app.use(config.api.prefix, routes);

// Root endpoint
app.get('/', (req, res) => {
  res.json({
    success: true,
    message: 'ISC Multivendor API',
    version: '1.0.0',
    endpoints: {
      health: `${config.api.prefix}/health`,
      vendors: `${config.api.prefix}/vendors`,
      products: `${config.api.prefix}/products`
    }
  });
});

// Error handling
app.use(notFound);
app.use(errorHandler);

module.exports = app;
