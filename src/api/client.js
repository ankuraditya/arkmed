const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api/v1'
async function request(path, options = {}) {
  const controller = new AbortController()
  let timedOut = false
  const timer = setTimeout(() => { timedOut = true; controller.abort() }, 15000)
  const abortFromCaller = () => controller.abort()
  options.signal?.addEventListener('abort', abortFromCaller, { once: true })
  try {
    const response = await fetch(`${API_BASE}${path}`, { ...options, signal: controller.signal, headers: { Accept: 'application/json', ...options.headers } })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors||{}).flat()[0] || 'Something went wrong. Please try again.')
    return payload
  } catch (error) {
    if (error.name === 'AbortError' && timedOut) throw new Error('The request took too long. Please check your connection and try again.',{cause:error})
    throw error
  } finally { clearTimeout(timer); options.signal?.removeEventListener('abort', abortFromCaller) }
}
export const api = {
  home: () => request('/home'),
  settings: () => request('/settings/public'),
  page: slug => request(`/pages/${slug}`),
  createEnquiry: body => request('/enquiries', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }),
  medicines: (query = '') => request(`/medicines${query}`),
  medicine: (slug,options={}) => request(`/medicines/${slug}`,options),
  uploadPrescriptions: files => {
    const body = new FormData()
    files.forEach(file => body.append('files[]', file))
    return request('/prescription-uploads', { method: 'POST', body })
  },
  createOrder: body => request('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }),
  trackOrder: body => request('/orders/track', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }),
}
