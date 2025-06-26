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
        <div class="modal-header bg-light border-0">
          <h5 class="modal-title" id="orderDetailsLabel">
            Detalhes do Pedido: <span class="text-secondary fw-bold">{{ order.identify }}</span>
          </h5>
          <button type="button" class="btn-close" @click="closeDetails"></button>
        </div>

        <div class="modal-body">
          <form @submit.prevent="updateStatus" class="row align-items-center mb-4">
            <div class="col-auto">
              <label for="status" class="form-label mb-0">Status:</label>
              <select v-model="status" id="status" class="form-select">
                <option value="open">Aberto</option>
                <option value="done">Completo</option>
                <option value="rejected">Rejeitado</option>
                <option value="working">Andamento</option>
                <option value="canceled">Cancelado</option>
                <option value="delivering">Em trânsito</option>
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-primary" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                Atualizar
              </button>
            </div>
          </form>

          <section class="mb-4">
            <h6 class="text-uppercase text-muted">Informações do Pedido</h6>
            <dl class="row">
              <dt class="col-sm-4">Número:</dt>
              <dd class="col-sm-8">{{ order.identify }}</dd>

              <dt class="col-sm-4">Total:</dt>
              <dd class="col-sm-8">R$ {{ total }}</dd>

              <dt class="col-sm-4">Status:</dt>
              <dd class="col-sm-8">
                <span :class="['badge', statusClass]">{{ order.status_label }}</span>
              </dd>

              <dt class="col-sm-4">Data / Hora:</dt>
              <dd class="col-sm-8">{{ order.date_br }} às {{ order.hour }}</dd>
            </dl>
          </section>

          <section v-if="order.client" class="mb-4">
            <h6 class="text-uppercase text-muted">Cliente</h6>
            <dl class="row">
              <dt class="col-sm-4">Nome:</dt>
              <dd class="col-sm-8">{{ order.client.name }}</dd>

              <dt class="col-sm-4">Contato:</dt>
              <dd class="col-sm-8">{{ order.client.telefone }}</dd>

              <dt class="col-sm-4">Endereço:</dt>
              <dd class="col-sm-8">{{ order.client.endereco }}</dd>

              <dt class="col-sm-4">Instagram:</dt>
              <dd class="col-sm-8">{{ order.client.instagran }}</dd>

              <dt class="col-sm-4">Comentário:</dt>
              <dd class="col-sm-8"><p class="bg-light p-2 rounded">{{ order.comment }}</p></dd>
            </dl>
          </section>

          <div class="row">
            <div class="col-md-6 mb-4">
              <h6 class="text-uppercase text-muted">Produtos</h6>
              <ul class="list-unstyled">
                <li v-for="(prod, i) in order.products" :key="i" class="d-flex align-items-center mb-3">
                  <img :src="prod.image" :alt="prod.title" class="me-3 rounded" style="width: 60px; height: 60px; object-fit: cover;" />
                  <div>
                    <div class="fw-semibold">{{ prod.title }}</div>
                    <small>Qtd: {{ prod.quantity }}</small>
                  </div>
                </li>
              </ul>
            </div>

            <div class="col-md-6 mb-4">
              <h6 class="text-uppercase text-muted">Avaliações</h6>
              <ul class="list-unstyled">
                <li v-for="(ev, idx) in order.evaluations" :key="idx" class="mb-3 p-2 border rounded">
                  <div><strong>Nota:</strong> {{ ev.stars }}/4</div>
                  <div><strong>Comentário:</strong> {{ ev.comment }}</div>
                </li>
              </ul>
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
    display: { required: true },
    order: { type: Object, required: true }
  },
  computed: {
    total() {
      return this.order.total.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }
  },
  data() {
    return { status: this.order.status, loading: false };
  },
  methods: {
    closeDetails() {
      this.$emit('closeDetails');
    },
    statusClass() {
      const map = {
        open: 'bg-info text-white',
        done: 'bg-success text-white',
        rejected: 'bg-danger text-white',
        working: 'bg-warning text-dark',
        canceled: 'bg-secondary text-white',
        delivering: 'bg-primary text-white'
      };
      return map[this.order.status] || 'bg-light text-dark';
    },
    updateStatus() {
      this.loading = true;
      axios.patch('/api/v1/my-orders', { status: this.status, identify: this.order.identify })
        .then(() => this.$emit('statusUpdated'))
        .catch(() => alert('Erro ao atualizar o status'))
        .finally(() => this.loading = false);
    }
  },
  watch: {
    order(newOrder) { this.status = newOrder.status; }
  }
};
</script>

<style scoped>
.modal-content {
    color:#000;
  border-radius: 0.5rem;
}
.modal-header {
  border-bottom: none;
  padding: 1rem 1.5rem;
}
.modal-body {
  padding: 1.5rem;
  background-color: #ffffff;
  max-height: 70vh;
  overflow-y: auto;
}
h6.text-uppercase {
  font-size: 0.75rem;
  letter-spacing: 0.1em;
}
.badge {
  font-size: 0.9rem;
  padding: 0.4em 0.75em;
}
</style>
