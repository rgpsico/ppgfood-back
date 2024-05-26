<template>
    <div id="exampleModalLive" class="modal fade show" tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel" :style="{display: display}">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLiveLabel" style="color:#000;">
                        Detalhes do Pedido: 
                        <span style="color:red; font-weight:bold; font-size:25px;">{{ order.identify }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="closeDetails">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" method="POST" class="form form-inline" @submit.prevent="updateStatus" style="margin-bottom: 20px;">
                        <label for="status" style="margin-right: 10px;">Status:</label>
                        <select name="status" class="form-control" v-model="status" style="margin-right: 10px;">
                            <option value="open">Aberto</option>
                            <option value="done">Completo</option>
                            <option value="rejected">Rejeitado</option>
                            <option value="working">Andamentos</option>
                            <option value="canceled">Cancelado</option>
                            <option value="delivering">Em transito</option>
                        </select>
                        <button type="submit" class="btn btn-info" :disabled="loading">
                            Atualizar Status
                        </button>
                    </form>
                    <div class="row">
                        <div class="col-md-6">
                            <ul style="color:#000; list-style:none; padding: 0;">
                                <li style="margin-bottom: 10px;">
                                    <b>Número do pedido:</b> 
                                    <span style="color:red; font-weight:bold;">{{ order.identify }}</span>
                                </li>
                                <li style="margin-bottom: 10px;">
                                    <b>Total:</b> R$ {{ total }}
                                </li>
                                <li style="margin-bottom: 10px;">
                                    <b>Status:</b> <span :class="statusClass">{{ order.status_label }}</span>
                          
                                </li>
                                <li style="margin-bottom: 20px;">
                                    <b>Data:</b> {{ order.date_br }} | <b>Hora:</b> {{ order.hour }}
                                </li>
                                <li>
                                    <span style="font-weight:bold;">Cliente:</span>
                                    <ul style="list-style:none; padding: 0; margin: 10px 0;">
                                        <li><b>Nome:</b> {{ order.client.name }}</li>
                                        <li><b>Contato:</b> {{ order.client.telefone }}</li>
                                        <li><b>Endereço:</b> {{ order.client.endereco }}</li>
                                        <li><b>Instagram:</b> {{ order.client.instagran }}</li>
                                        <li style="margin-top: 10px;">
                                            <b>Comentário:</b><br>
                                            <p style="background:#ddd; color:dark; font-size:14px; padding: 10px;">{{order.comment}}</p>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <div>
                                <b>Produtos:</b>
                                <ul style="margin-top:10px; padding:0 20px; list-style:none;">
                                    <li v-for="(product, index) in order.products" :key="index" style="margin-bottom:10px; display: flex; align-items: center;">
                                        <img :src="product.image" :alt="product.title" style="max-width:100px; margin-right: 10px;">
                                        <span style="font-weight:bold; font-size:20px; color:red;">{{ product.title }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div style="margin-top: 20px;">
                                <b>Avaliações:</b>
                                <ul style="padding: 0; list-style: none;">
                                    <li v-for="(evaluation, index) in order.evaluations" :key="index" style="margin-bottom: 10px;">
                                        <b>Nota:</b> {{ evaluation.stars }}/4
                                        <br><b>Comentário:</b> {{ evaluation.comment }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
b{
    color:#000;
} 
.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-body-content {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 15px;
}

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
<script>
export default {
    props: {
        display: {
            required: true
        },
        order: {
            type: Object,
            required: true
        }
    },
    computed: {
        total () {
            return this.order.total.toLocaleString('pt-br', {minimumFractionDigits: 2})
        }
    },
    data() {
        return {
            status: '',
            loading: false
        }
    },
    methods: {
        closeDetails () {
            this.$emit('closeDetails')
        },
        statusClass() {
            switch (this.order.status) {
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
        updateStatus() {
            this.loading = true

            axios.patch('/api/v1/my-orders', {
                status: this.status,
                identify: this.order.identify
            })
            .then(response => this.$emit('statusUpdated'))
        
            .catch(error => alert('error'))
            .finally(() => this.loading = false)
        }
    },
    watch: {
        order () {
            this.status = this.order.status
           
        }
    },
}
</script>

