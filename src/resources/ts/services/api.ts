import axios from 'axios'

const api = axios.create({
  withCredentials: true,
  withXSRFToken: true, // ✅ recommended by Sanctum docs
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

export default api