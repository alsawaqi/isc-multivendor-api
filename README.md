# ISC Multivendor API

A RESTful API for managing multiple vendors and their products. This API provides endpoints for vendor management, product catalog, and multivendor operations.

## Features

- ✅ Vendor Management (CRUD operations)
- ✅ Product Management (CRUD operations)
- ✅ RESTful API design
- ✅ JSON responses
- ✅ Error handling
- ✅ CORS enabled

## Prerequisites

- Node.js (v14 or higher)
- npm or yarn

## Installation

1. Clone the repository:
```bash
git clone https://github.com/alsawaqi/isc-multivendor-api.git
cd isc-multivendor-api
```

2. Install dependencies:
```bash
npm install
```

3. Create environment file:
```bash
cp .env.example .env
```

4. Configure environment variables in `.env`:
```
PORT=3000
NODE_ENV=development
```

## Usage

### Development Mode (with auto-reload)
```bash
npm run dev
```

### Production Mode
```bash
npm start
```

The API will be available at `http://localhost:3000`

## API Endpoints

### Base URL
```
http://localhost:3000/api/v1
```

### Health Check
```
GET /api/v1/health
```

### Vendors

#### Get All Vendors
```
GET /api/v1/vendors
```

#### Get Vendor by ID
```
GET /api/v1/vendors/:id
```

#### Create Vendor
```
POST /api/v1/vendors
Content-Type: application/json

{
  "name": "Vendor Name",
  "email": "vendor@example.com",
  "phone": "+1234567890",
  "address": "123 Main St, City, Country",
  "status": "active"
}
```

#### Update Vendor
```
PUT /api/v1/vendors/:id
Content-Type: application/json

{
  "name": "Updated Vendor Name",
  "email": "updated@example.com",
  "phone": "+1234567890",
  "address": "456 Updated St, City, Country",
  "status": "active"
}
```

#### Delete Vendor
```
DELETE /api/v1/vendors/:id
```

### Products

#### Get All Products
```
GET /api/v1/products
GET /api/v1/products?vendorId=1  # Filter by vendor
```

#### Get Product by ID
```
GET /api/v1/products/:id
```

#### Create Product
```
POST /api/v1/products
Content-Type: application/json

{
  "name": "Product Name",
  "description": "Product description",
  "price": 99.99,
  "vendorId": 1,
  "sku": "PROD-001",
  "category": "Electronics",
  "stock": 100
}
```

#### Update Product
```
PUT /api/v1/products/:id
Content-Type: application/json

{
  "name": "Updated Product Name",
  "description": "Updated description",
  "price": 89.99,
  "stock": 150
}
```

#### Delete Product
```
DELETE /api/v1/products/:id
```

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "error": "Error details (in development mode)"
}
```

## Project Structure

```
isc-multivendor-api/
├── src/
│   ├── config/
│   │   └── config.js          # Configuration settings
│   ├── controllers/
│   │   ├── vendor.controller.js   # Vendor business logic
│   │   └── product.controller.js  # Product business logic
│   ├── models/
│   │   ├── vendor.model.js    # Vendor data model
│   │   └── product.model.js   # Product data model
│   ├── routes/
│   │   ├── index.js           # Main routes
│   │   ├── vendor.routes.js   # Vendor routes
│   │   └── product.routes.js  # Product routes
│   ├── middleware/
│   │   └── errorHandler.js    # Error handling middleware
│   ├── app.js                 # Express app configuration
│   └── server.js              # Server entry point
├── .env.example               # Example environment variables
├── .gitignore                 # Git ignore file
├── package.json               # Project dependencies
└── README.md                  # This file
```

## Technology Stack

- **Node.js** - Runtime environment
- **Express.js** - Web framework
- **CORS** - Cross-origin resource sharing
- **dotenv** - Environment variable management
- **nodemon** - Development auto-reload

## Data Storage

Currently, the API uses in-memory storage for demonstration purposes. For production use, integrate a database like:
- MongoDB
- PostgreSQL
- MySQL
- Redis

## Future Enhancements

- [ ] Database integration
- [ ] Authentication & Authorization
- [ ] API rate limiting
- [ ] Input validation with express-validator
- [ ] Unit and integration tests
- [ ] API documentation with Swagger
- [ ] Logging with Winston
- [ ] Docker support
- [ ] CI/CD pipeline

## License

ISC

## Author

Abdallah Al Sawaqi
