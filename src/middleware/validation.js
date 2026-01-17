// Basic validation utilities

const isValidEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

const isValidPhone = (phone) => {
  // Accepts international format with + and digits
  const phoneRegex = /^\+?[\d\s\-()]+$/;
  return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10;
};

const isPositiveNumber = (value) => {
  return typeof value === 'number' && value > 0;
};

module.exports = {
  isValidEmail,
  isValidPhone,
  isPositiveNumber
};
