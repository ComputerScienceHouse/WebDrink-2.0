var io = require('socket.io').listen(3000);

io.sockets.on('connection', function (socket) {
  console.log('user connected');

  socket.on('ibutton', function (data) {
  	console.log('on: ibutton', data);
  	socket.emit('ibutton_recv', 'OK');
  	// socket.emit('ibutton_recv', 'NOT OK');
  });

  socket.on('machine', function (data) {
  	console.log('on: machine', data);
  	socket.emit('machine_recv', 'OK');
  	// socket.emit('machine_recv', 'NOT OK');
  });

  socket.on('drop', function (data) {
  	console.log('on: drop', data);
  	var delay = +data.delay || 0;
  	delay += 2; // Pretend some computation is happening
  	setTimeout(function() {
  		socket.emit('drop_recv', 'OK');
  	}, delay * 1000);
  	// socket.emit('drop_recv', 'NOT OK');
  });
});
