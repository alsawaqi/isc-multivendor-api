<script lang="ts" setup>
import VendorLayout from '../../components/layout/VendorLayout.vue'
import { ref } from 'vue'


const vendorName = ref<string>('John Doe')
const vendorEmail = ref<string>('')
const pageTitle = ref<string>('Vendor Products')
const pageDescription = ref<string>('Manage your vendor account and activities.')




</script>
<template>

    <VendorLayout :vendor-name="vendorName" :vendor-email="vendorEmail" >
          <VendorPageHeader
          :title="pageTitle"
          :description="pageDescription"
          icon="fa-solid fa-receipt"
        />


   </VendorLayout>
</template>
