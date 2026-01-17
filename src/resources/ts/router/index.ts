// resources/ts/router/index.ts
import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import LoginPage from '../pages/auth/LoginPage.vue'
import VendorDashboard from '../pages/dashboard/VendorDashboard.vue'
import VendorProducts from '../pages/dashboard/VendorProducts.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'login',
    component: LoginPage,
    meta: { guestOnly: true, title: 'Vendor Login' },
  },
  {
    path: '/dashboard',
    name: 'vendor.dashboard',
    component: VendorDashboard,
    meta: { requiresAuth: true, title: 'Vendor Dashboard' },
  },
   {
    path: '/products',
    name: 'vendor.products',
    component: VendorProducts,
    meta: { requiresAuth: true, title: 'Vendor Products' },
  },
  // later you can add:
  // { path: '/products', component: ProductsPage, meta: { requiresAuth: true } },
]

const router = createRouter({
  // If your SPA lives under /vendor, use: createWebHistory('/vendor')
  history: createWebHistory(),
  routes,
})

// Optional: set page title from route meta
router.afterEach((to) => {
  if (to.meta?.title) {
    document.title = String(to.meta.title) + ' | ISC Multi-Vendor'
  }
})

export default router
