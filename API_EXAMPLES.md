# API Testing Examples

This file contains example commands to test all API endpoints.

## Setup

Start the server:
```bash
npm start
```

Or for development with auto-reload:
```bash
npm run dev
```

## Test Commands

### 1. Health Check
```bash
curl http://localhost:3000/api/v1/health
```

### 2. Create Vendor
```bash
curl -X POST http://localhost:3000/api/v1/vendors \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tech Solutions Inc",
    "email": "contact@techsolutions.com",
    "phone": "+1234567890",
    "address": "123 Tech Street, Silicon Valley, CA",
    "status": "active"
  }'
```

### 3. Get All Vendors
```bash
curl http://localhost:3000/api/v1/vendors
```

### 4. Get Vendor by ID
```bash
curl http://localhost:3000/api/v1/vendors/1
```

### 5. Update Vendor
```bash
curl -X PUT http://localhost:3000/api/v1/vendors/1 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Tech Solutions",
    "email": "newemail@techsolutions.com"
  }'
```

### 6. Create Product
```bash
curl -X POST http://localhost:3000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium Laptop",
    "description": "High-performance laptop for professionals",
    "price": 1299.99,
    "vendorId": 1,
    "sku": "LAPTOP-PRO-001",
    "category": "Electronics",
    "stock": 50
  }'
```

### 7. Get All Products
```bash
curl http://localhost:3000/api/v1/products
```

### 8. Get Products by Vendor
```bash
curl http://localhost:3000/api/v1/products?vendorId=1
```

### 9. Get Product by ID
```bash
curl http://localhost:3000/api/v1/products/1
```

### 10. Update Product
```bash
curl -X PUT http://localhost:3000/api/v1/products/1 \
  -H "Content-Type: application/json" \
  -d '{
    "price": 1199.99,
    "stock": 75
  }'
```

### 11. Delete Product
```bash
curl -X DELETE http://localhost:3000/api/v1/products/1
```

### 12. Delete Vendor
```bash
curl -X DELETE http://localhost:3000/api/v1/vendors/1
```

## Validation Tests

### Test Invalid Email
```bash
curl -X POST http://localhost:3000/api/v1/vendors \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "invalid-email",
    "phone": "+1234567890"
  }'
```

### Test Invalid Phone
```bash
curl -X POST http://localhost:3000/api/v1/vendors \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test",
    "email": "test@example.com",
    "phone": "123"
  }'
```

### Test Negative Price
```bash
curl -X POST http://localhost:3000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Product",
    "price": -50,
    "vendorId": 1,
    "sku": "SKU-001"
  }'
```

### Test Non-existent Vendor
```bash
curl -X POST http://localhost:3000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Product",
    "price": 50,
    "vendorId": 999,
    "sku": "SKU-001"
  }'
```

### Test Duplicate SKU
```bash
# First, create a product
curl -X POST http://localhost:3000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Product 1",
    "price": 50,
    "vendorId": 1,
    "sku": "UNIQUE-SKU"
  }'

# Then try to create another with same SKU
curl -X POST http://localhost:3000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Product 2",
    "price": 60,
    "vendorId": 1,
    "sku": "UNIQUE-SKU"
  }'
```
