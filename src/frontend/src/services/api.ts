import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Only clear the token — do NOT hard-redirect.
      // The Vue router navigation guard will detect the missing token
      // and redirect to the correct login page on the next navigation.
      localStorage.removeItem('token')

      // Use Vue Router push instead of window.location.href to avoid
      // full page reload which kills all app state.
      // Only redirect if we're currently on a merchant (non-super-admin) page
      const path = window.location.pathname
      if (!path.startsWith('/super-admin') && !path.startsWith('/customer') && path !== '/login') {
        // Use a flag to prevent multiple redirects from concurrent 401s
        if (!(window as any).__redirectingToLogin) {
          (window as any).__redirectingToLogin = true
          setTimeout(() => {
            (window as any).__redirectingToLogin = false
            window.location.href = '/login'
          }, 100)
        }
      }
    }
    return Promise.reject(error)
  }
)

export default api
