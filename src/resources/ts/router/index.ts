import { createRouter, createWebHistory } from 'vue-router'
import { useVendorAuth } from '../composables/useVendorAuth'

const router = createRouter({
  history: createWebHistory('/'), // change to '/vendor' if mounted at /vendor
  routes: [
    {
      path: '/',
      name: 'vendor.login',
      component: () => import('../pages/auth/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'vendor.dashboard',
      component: () => import('../pages/dashboard/VendorDashboard.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/products',
      name: 'vendor.products',
      component: () => import('../pages/dashboard/VendorProducts.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/viewproducts',
      name: 'vendor.viewproducts',
      component: () => import('../pages/dashboard/ViewVendorProducts.vue'),
      meta: { requiresAuth: true },
    },


    
    // ✅ Error page (for API failures, permission errors, etc.)
    {
      path: '/error',
      name: 'vendor.error',
      component: () => import('../pages/system/ErrorPage.vue'),
      meta: { public: true },
    },

    // ✅ 404 must be last
    {
      path: '/:pathMatch(.*)*',
      name: 'vendor.notfound',
      component: () => import('../pages/system/NotFoundPage.vue'),
      meta: { public: true },
    },
  ],

  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useVendorAuth()

  try {
    await auth.init()
  } catch {
    // If auth init fails (server down, etc.) you can optionally send to error page
    // But avoid looping if you're already going there
    if (to.name !== 'vendor.error') {
      return { name: 'vendor.error', query: { code: 'INIT_FAILED' } }
    }
  }

  // If the route is public (error / notfound), don't force redirects
  if (to.meta.public) return true

  if (to.meta.requiresAuth && !auth.isAuthed.value) {
    return { name: 'vendor.login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthed.value) {
    return { name: 'vendor.dashboard' }
  }

  return true
})

export default router
