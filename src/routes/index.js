const express = require('express');
const router = express.Router();

// Import route modules
const vendorRoutes = require('./vendor.routes');
const productRoutes = require('./product.routes');

// Mount routes
router.use('/vendors', vendorRoutes);
router.use('/products', productRoutes);

// Health check endpoint
router.get('/health', (req, res) => {
  res.json({
    success: true,
    message: 'ISC Multivendor API is running',
    timestamp: new Date().toISOString()
  });
});

module.exports = router;
