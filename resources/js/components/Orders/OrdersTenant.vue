<template>
    <div>
        <div class="card-header">
            <form action="#" method="POST" class="form form-inline">
                <label for="status">Status:</label>
                <select name="status" class="form-control" v-model="status">
                    <option value="all">Todos</option>
                    <option value="open">Aberto</option>
                    <option value="done">Completo</option>
                    <option value="rejected">Rejeitados</option>
                    <option value="working">Andamento</option>
                    <option value="canceled">Cancelado</option>
                    <option value="delivering">Em transito</option>
                </select> |
                <div class="form-group">
                    <label for="date">Data:</label>
                    <input type="date" class="form-control" v-model="dateFilter">
                </div>
            </form>
        </div>
        <div class="card-body">
            <table class="table table-condensed">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th width="270">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(order, index) in orders.data" :key="index">
                        <td>{{ order.identify }}</td>
                        <td><span :class="getStatusClass(order.status)">{{ order.status_label }}</span></td>
    
                        <td>{{ order.date_br }}</td>
                        <td>
                            <!-- <detail-order :order="order" :display="'none'"></detail-order> -->
                            <a href="#" @click.prevent="openDetails(order)" class="btn btn-info">Detalhes</a>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="loadingOrders">Carregando seus pedidos</div>
            <div v-else-if="orders.data.length == 0">Nenhum Pedido</div>
        </div>

        <detail-order :order="order" :display="displayOrder" @closeDetails="closeDetails" @statusUpdated="statusUpdated"></detail-order>
    </div>
</template>



<script>


import Bus from '../../bus'
import DetailOrder from './_partials/DetailOrder'

export default {
    mounted() {
      
        this.getOrders()

        Bus.$on('order.created', (order) => {               
            this.orders.data.unshift(order)
        })
    },
    data() {
        return {
            orders: {
                data: []
            },
            loadingOrders: false,
            dateFilter: new Date().getFullYear()+'-'+("0"+(new Date().getMonth()+1)).slice(-2)+'-'+("0"+new Date().getDate()).slice(-2),
            status: 'all',
            order: {
                identify: "",
                total: "",
                status: "",
                status_label: "",
                date: "",
                date_br: "",
                company: {
                    name: "",
                    image: "",
                    uuid: "",
                    contact: "",
                },
                client: {
                    name: "",
                    email: ""
                },
                table: "",
                products: [],
                evaluations: []
            },
            displayOrder: 'none',
        }
    },
    methods: {
    getStatusClass(status) {
        switch (status) {
            case 'open':
                return 'status-label status-open';
            case 'done':
                return 'status-label status-done';
            case 'rejected':
                return 'status-label status-rejected';
            case 'working':
                return 'status-label status-working';
            case 'canceled':
                return 'status-label status-canceled';
            case 'delivering':
                return 'status-label status-delivering';
            default:
                return 'status-label';
        }
    },
        getOrders () {        
            this.loadingOrders = true
           
            axios.get('/api/v1/my-orders', {params: {
                status: this.status,
                date: this.dateFilter
            }})
                    .then(response => this.orders = response.data)
                    .catch(error => console.log(error))
                    .finally(() => this.loadingOrders = false)
        },

        reset () {
            this.orders = {data: []}
        },

        statusUpdated (params) {
            this.closeDetails()
            this.getOrders()
        },

        openDetails(order) {
            this.order = order;
            this.displayOrder = 'block'
        },

        closeDetails() {
            this.order = {
                identify: "",
                total: "",
                status: "",
                status_label: "",
                date: "",
                date_br: "",
                company: {
                    name: "",
                    image: "",
                    uuid: "",
                    contact: "",
                },
                client: {
                    name: "",
                    email: ""
                },
                table: "",
                products: [],
                evaluations: []
            },
            this.displayOrder = 'none'
        },
    },
    watch: {
        status () {
            return this.getOrders()
        },

        dateFilter () {
            return this.getOrders()
        }
    },
    components: {
        DetailOrder
    }
}

const tenantId = window.Laravel.tenantId;



window.Echo.channel('order-created.'+tenantId)
    .listen('OrderCreated', (e) => { 

     
       let notificationAudio = document.getElementById('notificationSound');
      
       notificationAudio.play();

    
        Vue.$vToastify.success(`Novo pedido ${e.order.identify}`, 'Novo Pedido');
        Bus.$emit('order.created', e.order);

    });

</script>

<style scoped>
    .status-label {
        padding: 5px 10px;
        border-radius: 4px;
        color: white;
    }

    .status-open {
        background-color: blue;
    }

    .status-done {
        background-color: green;
    }

    .status-rejected {
        background-color: red;
    }

    .status-working {
        background-color: orange;
    }

    .status-canceled {
        background-color: gray;

        
    }

    .status-delivering {
        background-color: purple;
    }
</style>