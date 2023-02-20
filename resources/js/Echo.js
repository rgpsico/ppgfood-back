import Vue from 'vue'
import Bus from './bus'

// get id tenant
const tenantId = window.Laravel.tenantId;

window.Echo.channel(`order-created`)
.listen('OrderCreated', (e) => {
   console.log(e)
    Vue.$vToastify.success(`Novo pedido ${e.order.identify}`, 'Novo Pedido')
    Bus.$emit('order.created', e.order)

   
})