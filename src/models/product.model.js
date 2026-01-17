// In-memory storage for products (replace with database in production)
let products = [];
let nextId = 1;

class Product {
  constructor(name, description, price, vendorId, sku, category, stock = 0) {
    this.id = nextId++;
    this.name = name;
    this.description = description;
    this.price = price;
    this.vendorId = vendorId;
    this.sku = sku;
    this.category = category;
    this.stock = stock;
    this.createdAt = new Date().toISOString();
    this.updatedAt = new Date().toISOString();
  }
}

// CRUD operations
const productModel = {
  getAll: (vendorId = null) => {
    if (vendorId) {
      return products.filter(p => p.vendorId === parseInt(vendorId));
    }
    return products;
  },
  
  getById: (id) => products.find(p => p.id === parseInt(id)),
  
  create: (data) => {
    const product = new Product(
      data.name,
      data.description,
      data.price,
      data.vendorId,
      data.sku,
      data.category,
      data.stock
    );
    products.push(product);
    return product;
  },
  
  update: (id, data) => {
    const index = products.findIndex(p => p.id === parseInt(id));
    if (index === -1) return null;
    
    products[index] = {
      ...products[index],
      ...data,
      id: products[index].id,
      createdAt: products[index].createdAt,
      updatedAt: new Date().toISOString()
    };
    return products[index];
  },
  
  delete: (id) => {
    const index = products.findIndex(p => p.id === parseInt(id));
    if (index === -1) return false;
    
    products.splice(index, 1);
    return true;
  }
};

module.exports = productModel;
