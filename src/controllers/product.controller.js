const productModel = require('../models/product.model');
const vendorModel = require('../models/vendor.model');
const { isPositiveNumber } = require('../middleware/validation');

const productController = {
  // Get all products (optionally filter by vendor)
  getAllProducts: (req, res) => {
    try {
      const { vendorId } = req.query;
      const products = productModel.getAll(vendorId);
      res.json({
        success: true,
        count: products.length,
        data: products
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error fetching products',
        error: error.message
      });
    }
  },

  // Get product by ID
  getProductById: (req, res) => {
    try {
      const product = productModel.getById(req.params.id);
      if (!product) {
        return res.status(404).json({
          success: false,
          message: 'Product not found'
        });
      }
      res.json({
        success: true,
        data: product
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error fetching product',
        error: error.message
      });
    }
  },

  // Create new product
  createProduct: (req, res) => {
    try {
      const { name, description, price, vendorId, sku, category, stock } = req.body;

      // Validation
      if (!name || !price || !vendorId || !sku) {
        return res.status(400).json({
          success: false,
          message: 'Name, price, vendorId, and sku are required'
        });
      }

      if (!isPositiveNumber(price)) {
        return res.status(400).json({
          success: false,
          message: 'Price must be a positive number'
        });
      }

      // Check if vendor exists
      const vendor = vendorModel.getById(vendorId);
      if (!vendor) {
        return res.status(400).json({
          success: false,
          message: 'Vendor not found'
        });
      }

      // Check SKU uniqueness
      const existingProduct = productModel.getAll().find(p => p.sku === sku);
      if (existingProduct) {
        return res.status(400).json({
          success: false,
          message: 'SKU already exists'
        });
      }

      const product = productModel.create({ name, description, price, vendorId, sku, category, stock });
      res.status(201).json({
        success: true,
        message: 'Product created successfully',
        data: product
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error creating product',
        error: error.message
      });
    }
  },

  // Update product
  updateProduct: (req, res) => {
    try {
      const { name, description, price, vendorId, sku, category, stock } = req.body;
      const product = productModel.update(req.params.id, { name, description, price, vendorId, sku, category, stock });
      
      if (!product) {
        return res.status(404).json({
          success: false,
          message: 'Product not found'
        });
      }

      res.json({
        success: true,
        message: 'Product updated successfully',
        data: product
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error updating product',
        error: error.message
      });
    }
  },

  // Delete product
  deleteProduct: (req, res) => {
    try {
      const deleted = productModel.delete(req.params.id);
      
      if (!deleted) {
        return res.status(404).json({
          success: false,
          message: 'Product not found'
        });
      }

      res.json({
        success: true,
        message: 'Product deleted successfully'
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error deleting product',
        error: error.message
      });
    }
  }
};

module.exports = productController;
