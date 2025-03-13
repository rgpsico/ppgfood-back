<template>
    <v-app>
      <v-container>
        <v-card>
          <v-card-title>Lista de Entregadores</v-card-title>
          <v-data-table :headers="headers" :items="entregadores"></v-data-table>
        </v-card>
      </v-container>
    </v-app>
  </template>
  
  <script>
  export default {
    data() {
      return {
        entregadores: [],
        headers: [
          { text: "Nome", value: "nome" },
          { text: "Telefone", value: "telefone" }
        ]
      };
    },
    mounted() {
      this.carregarEntregadores();
    },
    methods: {
      carregarEntregadores() {
        fetch("/api/entregadores")
          .then((res) => res.json())
          .then((data) => {
            this.entregadores = data;
          });
      }
    }
  };
  </script>
  