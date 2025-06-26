require('./bootstrap');

window.Vue = require('vue');
import VueToastify from "vue-toastify";
Vue.use(VueToastify);

Vue.component('orders-tenant', require('./components/Orders/OrdersTenant.vue').default);
// Vue.component("entregadores", require("./components/Entregadores.vue").default);

const app = new Vue({
    el: '#app',
});
