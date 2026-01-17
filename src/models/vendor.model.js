// In-memory storage for vendors (replace with database in production)
let vendors = [];
let nextId = 1;

class Vendor {
  constructor(name, email, phone, address, status = 'active') {
    this.id = nextId++;
    this.name = name;
    this.email = email;
    this.phone = phone;
    this.address = address;
    this.status = status;
    this.createdAt = new Date().toISOString();
    this.updatedAt = new Date().toISOString();
  }
}

// CRUD operations
const vendorModel = {
  getAll: () => vendors,
  
  getById: (id) => vendors.find(v => v.id === parseInt(id)),
  
  create: (data) => {
    const vendor = new Vendor(
      data.name,
      data.email,
      data.phone,
      data.address,
      data.status
    );
    vendors.push(vendor);
    return vendor;
  },
  
  update: (id, data) => {
    const index = vendors.findIndex(v => v.id === parseInt(id));
    if (index === -1) return null;
    
    vendors[index] = {
      ...vendors[index],
      ...data,
      id: vendors[index].id,
      createdAt: vendors[index].createdAt,
      updatedAt: new Date().toISOString()
    };
    return vendors[index];
  },
  
  delete: (id) => {
    const index = vendors.findIndex(v => v.id === parseInt(id));
    if (index === -1) return false;
    
    vendors.splice(index, 1);
    return true;
  }
};

module.exports = vendorModel;
