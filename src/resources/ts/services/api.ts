import axios from 'axios'

const api = axios.create({
  withCredentials: true,
  headers: { 'X-Requested-With': 'XMLHttpRequest' },

  // IMPORTANT: Laravel’s default CSRF cookie/header names
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

export default api
