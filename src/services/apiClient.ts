import axios from 'axios'
import { apiBase, getToken } from './http'

const apiClient = axios.create({
  baseURL: apiBase() || '/api',
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default apiClient
