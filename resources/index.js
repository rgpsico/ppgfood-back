const express = require('express');
const { disconnect } = require('process');

const app = express();

var server = require('http').createServer(app)


const io = require('socket.io')(server, {
    cors:{
        origin: "*"
    }
})


server.listen(3000, ()=>{
    console.log('server is running')
});

app.get('/', function(request, response){
    response.sendFile(__dirname + '/index.html')
})

io.on('connection', (socket) => {
    socket.on('sendChatToServer', (message)=> {
        console.log('enviando',message)
        io.sockets.emit('sendChatToClient', message)
    })

 
    socket.on('disconnect', (message)=>{
    io.emit('chat.message', message)
   })
})