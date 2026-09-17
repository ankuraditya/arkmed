export const categories = [
  { name: 'Tablets', slug: 'tablets', icon: '💊', tone: 'blue', image: '/categories/tablets.webp' },
  { name: 'Syrups', slug: 'syrups', icon: '🧴', tone: 'amber', image: '/categories/syrups.webp' },
  { name: 'Inhalers', slug: 'inhalers', icon: '🫁', tone: 'cyan', image: '/categories/inhalers.webp' },
  { name: 'Gels', slug: 'gels', icon: '🧴', tone: 'purple', image: '/categories/gels.webp' },
  { name: 'Ointments', slug: 'ointments', icon: '⚕', tone: 'green', image: '/categories/ointments.webp' },
  { name: 'Wellness', slug: 'wellness', icon: '🌿', tone: 'mint', image: '/categories/wellness.webp' },
]

// Non-production fixtures. These are replaced only after pharmacy verification.
export const sampleMedicines = [
  { id: 'demo-tablet-1', slug: 'sample-tablet', name: 'Sample tablet', category: 'tablets', detail: 'Strength pending verification', packSize: 'Pack size pending', prescription: false, stock: 'on_request' },
  { id: 'demo-syrup-1', slug: 'sample-syrup', name: 'Sample syrup', category: 'syrups', detail: 'Composition pending verification', packSize: 'Pack size pending', prescription: false, stock: 'on_request' },
  { id: 'demo-inhaler-1', slug: 'sample-inhaler', name: 'Sample inhaler', category: 'inhalers', detail: 'Strength pending verification', packSize: 'Pack size pending', prescription: true, stock: 'on_request' },
  { id: 'demo-gel-1', slug: 'sample-gel', name: 'Sample gel', category: 'gels', detail: 'Composition pending verification', packSize: 'Pack size pending', prescription: false, stock: 'on_request' },
  { id: 'demo-ointment-1', slug: 'sample-ointment', name: 'Sample ointment', category: 'ointments', detail: 'Strength pending verification', packSize: 'Pack size pending', prescription: true, stock: 'on_request' },
  { id: 'demo-wellness-1', slug: 'sample-wellness-item', name: 'Sample wellness item', category: 'wellness', detail: 'Details pending verification', packSize: 'Pack size pending', prescription: false, stock: 'on_request' },
]
