<template>
    <div
        id="orderDetailsModal"
        class="modal fade show"
        tabindex="-1"
        role="dialog"
        aria-labelledby="orderDetailsLabel"
        :style="{ display: display }"
    >
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsLabel">
                        Detalhes do Pedido:
                        <span class="order-identify">{{ order.identify }}</span>
                    </h5>
                    <button type="button" class="close" @click="closeDetails">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form
                        @submit.prevent="updateStatus"
                        class="form-inline status-form"
                    >
                        <label for="status">Status:</label>
                        <select
                            v-model="status"
                            class="form-control"
                            name="status"
                        >
                            <option value="open">Aberto</option>
                            <option value="done">Completo</option>
                            <option value="rejected">Rejeitado</option>
                            <option value="working">Andamento</option>
                            <option value="canceled">Cancelado</option>
                            <option value="delivering">Em trânsito</option>
                        </select>
                        <button
                            type="submit"
                            class="btn btn-info"
                            :disabled="loading"
                        >
                            Atualizar Status
                        </button>
                    </form>

                    <div class="row order-info">
                        <div class="col-md-6">
                            <ul class="order-details-list">
                                <li>
                                    <b>Número do pedido:</b>
                                    <span class="order-identify">{{
                                        order.identify
                                    }}</span>
                                </li>
                                <li><b>Total:</b> R$ {{ total }}</li>
                                <li>
                                    <b>Status:</b>
                                    <span :class="statusClass">{{
                                        order.status_label
                                    }}</span>
                                </li>
                                <li>
                                    <b>Data:</b> {{ order.date_br }} |
                                    <b>Hora:</b> {{ order.hour }}
                                </li>
                                <li v-if="order.client">
                                    <b>Cliente:</b>
                                    <ul class="client-details">
                                        <li>
                                            <b>Nome:</b> {{ order.client.name }}
                                        </li>
                                        <li>
                                            <b>Contato:</b>
                                            {{ order.client.telefone }}
                                        </li>
                                        <li>
                                            <b>Endereço:</b>
                                            {{ order.client.endereco }}
                                        </li>
                                        <li>
                                            <b>Instagram:</b>
                                            {{ order.client.instagran }}
                                        </li>
                                        <li>
                                            <b>Comentário:</b>
                                            <p class="order-comment">
                                                {{ order.comment }}
                                            </p>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <div>
                                <b>Produtos:</b>
                                <ul class="product-list">
                                    <li
                                        v-for="(product,
                                        index) in order.products"
                                        :key="index"
                                        class="product-item"
                                    >
                                        <img
                                            :src="product.image"
                                            :alt="product.title"
                                            class="product-image"
                                        />
                                        <span class="product-title">{{
                                            product.title
                                        }}</span>
                                        <span class="product-quantity"
                                            >Quantidade:
                                            {{ product.quantity }}</span
                                        >
                                    </li>
                                </ul>
                            </div>

                            <div class="evaluation-section">
                                <b>Avaliações:</b>
                                <ul class="evaluation-list">
                                    <li
                                        v-for="(evaluation,
                                        index) in order.evaluations"
                                        :key="index"
                                        class="evaluation-item"
                                    >
                                        <b>Nota:</b> {{ evaluation.stars }}/4
                                        <br /><b>Comentário:</b>
                                        {{ evaluation.comment }}
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
        total() {
            return this.order.total.toLocaleString("pt-br", {
                minimumFractionDigits: 2
            });
        }
    },
    data() {
        return {
            status: this.order.status,
            loading: false
        };
    },
    methods: {
        closeDetails() {
            this.$emit("closeDetails");
        },
        statusClass() {
            const statusClasses = {
                open: "status-open",
                done: "status-done",
                rejected: "status-rejected",
                working: "status-working",
                canceled: "status-canceled",
                delivering: "status-delivering"
            };
            return statusClasses[this.order.status] || "status-default";
        },
        updateStatus() {
            this.loading = true;
            axios
                .patch("/api/v1/my-orders", {
                    status: this.status,
                    identify: this.order.identify
                })
                .then(response => this.$emit("statusUpdated"))
                .catch(error => alert("Erro ao atualizar o status"))
                .finally(() => (this.loading = false));
        }
    },
    watch: {
        order(newOrder) {
            this.status = newOrder.status;
        }
    }
};
</script>

<style scoped>
body {
    color: #333;
}
/* Variáveis para facilitar a customização */
:root {
    --primary-color: #007bff;
    --secondary-color: #ff0000;
    --font-color: #333; /* Cor mais escura para melhor contraste */
    --background-color: #f8f9fa;
    --modal-max-height: 70vh;
}

/* Organizando o layout do modal */
.modal-header {
    background-color: var(--background-color);
}
.modal-content {
    color: #333;
}

.order-identify {
    font-weight: bold;
    color: var(--secondary-color);
    font-size: 25px;
}

.status-form {
    margin-bottom: 20px;
}

.order-info {
    padding: 10px 0;
}

.order-details-list,
.client-details,
.product-list,
.evaluation-list {
    list-style: none;
    padding: 0;
}

b {
    color: var(--font-color); /* Fonte mais escura */
}

.product-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.product-image {
    max-width: 100px;
    margin-right: 10px;
}

.order-comment {
    background: #ddd;
    padding: 10px;
    color: var(--font-color);
}

.evaluation-section {
    margin-top: 20px;
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-dialog {
        width: 100%;
        max-width: none;
    }
    .product-image {
        max-width: 70px;
    }
}
</style>
