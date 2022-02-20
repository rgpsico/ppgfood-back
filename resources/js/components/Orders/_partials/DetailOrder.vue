<template>
    <div id="exampleModalLive" class="modal fade show" tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel" :style="{display: display}">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLiveLabel" style="color:#000;">Detalhes do Pedido: 
                        <span style="color:red; font-weight:bold; color:red; font-size:25px; ">{{ order.identify }}</span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="closeDetails">
                    <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" method="POST" class="form form-inline" @submit.prevent="updateStatus">
                        <label for="status">Status:</label>
                        <select name="status" class="form-control" v-model="status">
                            <option value="open">Aberto</option>
                            <option value="done">Completo</option>
                            <option value="rejected">Rejeitado</option>
                            <option value="working">Andamento</option>
                            <option value="canceled">Cancelado</option>
                            <option value="delivering">Em transito</option>
                        </select> |
                        <button type="submit" class="btn btn-info" :disabled="loading">
                            Atualizar Status
                        </button>
                    </form>
                    <ul style="color:#000; text-decoration:none; list-style:none;" >
                        <li><b>Número do pedido:</b> <span style="color:red; font-weight:bold;">{{ order.identify }}</span></li>
                        <li><b>Total:</b> R$ {{ total }}</li>
                        <li><b>Status:</b> {{ order.status_label }}</li>
                        <li><b>Data:</b> {{ order.date_br }} | <b>Hora:</b> {{ order.hour }}</li>
                        <li>
                        <br/>
                            <span style="margin-top:10px; font-weight:bold;">Cliente:</span>
                            <ul  style="list-style:none; text-transform:capitalize;">
                                <li><b>Nome:</b> {{ order.client.name }}</li>
                                <!-- <li>image: {{ order.image }}</li> -->
                                <!-- <li>uuid: {{ order.uuid }}</li> -->
                                <li><b>Contato:</b> {{ order.client.telefone }}</li>
                                <li><b>endereço:</b> {{ order.client.endereco }}</li>
                                <li><b>instagran:</b> {{ order.client.instagran }}</li>
                                 <li><b>Comentario:</b>{{order.comment}} </li>
                            </ul>
                        </li>
                             
                         <br>
                         <br>
                        <li>
                            <b>Produtos:</b>
                            <ul style="margin-top:2px; padding:20px; list-style:none;">
                                <li v-for="(product, index) in order.products" :key="index" style="margin-bottom:10px;">
                                    <img :src="product.image" :alt="product.title" style="max-width:100px;">
                                   <span style="margin-left:10px; font-weight:bold; font-size:20px; color:red;">{{ product.title }}</span> 
                                </li>
                            </ul>
                        </li>
                        <li>
                            <b>Avaliações:</b>
                            <ul style="min-height:300px;">
                                <li v-for="(evaluation, index) in order.evaluations" :key="index">
                                    Nota: {{ evaluation.stars }}/4
                                    <br>Comentário: {{ evaluation.comment }}
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

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

