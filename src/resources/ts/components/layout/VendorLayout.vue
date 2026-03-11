<template>
  <div class="min-h-screen flex bg-slate-50 dark:bg-slate-900">

    <!-- Sidebar -->
    <VendorSidebar
      :open="isMobileSidebarOpen"
      :collapsed="isSidebarCollapsed"
      :active-key="activeKey"
      @navigate="onNavigate"
      @close="isMobileSidebarOpen = false"
    />

    <!-- MAIN AREA -->
    <div class="flex-1 flex flex-col min-w-0">

      <VendorTopbar
        :collapsed="isSidebarCollapsed"
        :vendor-name="vendorName"
        :vendor-email="vendorEmail"
        @toggleSidebar="isSidebarCollapsed = !isSidebarCollapsed"
        @toggleMobileSidebar="isMobileSidebarOpen = true"
      />

      <!-- PAGE CONTENT -->
      <main class="flex-1 px-4 sm:px-6 lg:px-8 py-5 space-y-4 overflow-y-auto">
        <slot />
      </main>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import VendorSidebar from './VendorSidebar.vue'
import VendorTopbar from './VendorTopbar.vue'

const props = defineProps<{
  vendorName?: string
  vendorEmail?: string
}>()

const isMobileSidebarOpen = ref(false)
const isSidebarCollapsed = ref(false)
const activeKey = ref('overview')

const onNavigate = (key: string) => {
  activeKey.value = key
  isMobileSidebarOpen.value = false
}
</script>
