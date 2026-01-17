const vendorModel = require('../models/vendor.model');
const { isValidEmail, isValidPhone } = require('../middleware/validation');

const vendorController = {
  // Get all vendors
  getAllVendors: (req, res) => {
    try {
      const vendors = vendorModel.getAll();
      res.json({
        success: true,
        count: vendors.length,
        data: vendors
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error fetching vendors',
        error: error.message
      });
    }
  },

  // Get vendor by ID
  getVendorById: (req, res) => {
    try {
      const vendor = vendorModel.getById(req.params.id);
      if (!vendor) {
        return res.status(404).json({
          success: false,
          message: 'Vendor not found'
        });
      }
      res.json({
        success: true,
        data: vendor
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error fetching vendor',
        error: error.message
      });
    }
  },

  // Create new vendor
  createVendor: (req, res) => {
    try {
      const { name, email, phone, address, status } = req.body;

      // Validation
      if (!name || !email || !phone) {
        return res.status(400).json({
          success: false,
          message: 'Name, email, and phone are required'
        });
      }

      if (!isValidEmail(email)) {
        return res.status(400).json({
          success: false,
          message: 'Invalid email format'
        });
      }

      if (!isValidPhone(phone)) {
        return res.status(400).json({
          success: false,
          message: 'Invalid phone format. Must be at least 10 digits'
        });
      }

      const vendor = vendorModel.create({ name, email, phone, address, status });
      res.status(201).json({
        success: true,
        message: 'Vendor created successfully',
        data: vendor
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error creating vendor',
        error: error.message
      });
    }
  },

  // Update vendor
  updateVendor: (req, res) => {
    try {
      const { name, email, phone, address, status } = req.body;
      const vendor = vendorModel.update(req.params.id, { name, email, phone, address, status });
      
      if (!vendor) {
        return res.status(404).json({
          success: false,
          message: 'Vendor not found'
        });
      }

      res.json({
        success: true,
        message: 'Vendor updated successfully',
        data: vendor
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error updating vendor',
        error: error.message
      });
    }
  },

  // Delete vendor
  deleteVendor: (req, res) => {
    try {
      const deleted = vendorModel.delete(req.params.id);
      
      if (!deleted) {
        return res.status(404).json({
          success: false,
          message: 'Vendor not found'
        });
      }

      res.json({
        success: true,
        message: 'Vendor deleted successfully'
      });
    } catch (error) {
      res.status(500).json({
        success: false,
        message: 'Error deleting vendor',
        error: error.message
      });
    }
  }
};

module.exports = vendorController;
