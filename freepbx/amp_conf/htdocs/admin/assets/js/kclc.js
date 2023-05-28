/*
 * HTML5 lightcycles
 * copyright (c) 2013 Jason Straughan - JDStraughan.com
 * MIT License - http://opensource.org/licenses/MIT
 */
canvas = document.getElementById("the-game");
context = canvas.getContext("2d");

enemy = {
  type: 'program',
  width: 8,
  height: 8,
  color: '#92F15F',
  history: [],
  current_direction: null
};

player = {
  type: 'user',
  width: 8,
  height: 8,
  color: '#58BEFF',
  history: [],
  current_direction: null
};

keys = {
  up: [38, 87],
  down: [40, 83],
  left: [37, 65],
  right: [39, 68],
  start_game: [13, 32]
};

lastKey = null;

game = {
  
  over: false,
  
  start: function() {
    cycle.resetPlayer();
    cycle.resetEnemy();
    game.over = false;
    player.current_direction = "left";
    game.resetCanvas();
  },
  
  stop: function(cycle) {
    game.over = true;
    context.fillStyle = '#FFF';
    context.font = (canvas.height / 15) + 'px sans-serif';
    context.textAlign = 'center';
    winner = cycle.type == 'program' ? 'USER' : 'PROGRAM';
    context.fillText('GAME OVER - ' + winner + ' WINS', canvas.width/2, canvas.height/2);
    context.fillText('press spacebar to contine', canvas.width/2, canvas.height/2 + (cycle.height * 3)); 
    cycle.color = "#F00";
  },
  
  newLevel: function() {
    cycle.resetPlayer();
    cycle.resetEnemy();
    this.resetCanvas();
  },
  
  resetCanvas: function() {
    context.clearRect(0, 0, canvas.width, canvas.height);
  }
  
};

cycle = {
  
  resetPlayer: function() {
    player.x = canvas.width - (canvas.width / (player.width / 2) + 4);
    player.y = (canvas.height / 2) + (player.height / 2);
    player.color = '#58BEFF';
    player.history = [];    
    player.current_direction = "left";
  },
  
  resetEnemy: function() {
    enemy.x = (canvas.width / (enemy.width / 2) - 4);
    enemy.y = (canvas.height / 2) + (enemy.height / 2);
    enemy.color = '#92F15F';
    enemy.history = [];
    enemy.current_direction = "right";
  },
  
  move: function(cycle, opponent) {
    switch(cycle.current_direction) {
      case 'up':
        cycle.y -= cycle.height;
        break;
      case 'down':
        cycle.y += cycle.height;
        break;
      case 'right':
        cycle.x += cycle.width;
        break;
      case 'left':
        cycle.x -= cycle.width;
        break;
    }
    if (this.checkCollision(cycle, opponent)) {
      game.stop(cycle);
    }
    coords = this.generateCoords(cycle);
    cycle.history.push(coords);
  },
  
  moveEnemy: function() {
    advisor = this.enemyPingDirections();
    if (advisor[enemy.current_direction] < enemy.width || Math.ceil(Math.random() * 10) == 5) {
      enemy.current_direction = advisor.best;
    }
    this.move(enemy, player);
  },
  
  enemyPingDirections: function() {
    pong = {
      up: 0,
      down: 0,
      left: 0,
      right: 0
    };
    // Up
    for (i = enemy.y - enemy.height; i>= 0; i -= enemy.height) {
      pong.up = enemy.y - i - enemy.width;
      if (this.isCollision(enemy.x, i)) break;
    }
    // Down
    for (i = enemy.y + enemy.height; i<= canvas.height; i += enemy.height) {
      pong.down = i - enemy.y - enemy.width;
      if (this.isCollision(enemy.x, i)) break;
    }
    // Left
    for (i = enemy.x - enemy.width; i>= 0; i -= enemy.width) {
      pong.left = enemy.x - i - enemy.width;
      if (this.isCollision(i, enemy.y)) break;
    }
    // Right
    for (i = enemy.x + enemy.width; i<= canvas.width; i += enemy.width) {
      pong.right = i - enemy.x - enemy.width;
      if (this.isCollision(i, enemy.y)) break;
    }
    var largest = {
      key: null,
      value: 0
    };
    for(var j in pong){
        if( pong[j] > largest.value ){
            largest.key = j;
            largest.value = pong[j];
        }
    }
    pong.best = largest.key;
    return pong;
  },

  checkCollision: function(cycle, opponent) {
    if ((cycle.x < (cycle.width / 2)) || 
        (cycle.x > canvas.width - (cycle.width / 2)) || 
        (cycle.y < (cycle.height / 2)) || 
        (cycle.y > canvas.height - (cycle.height / 2)) || 
        (cycle.history.indexOf(this.generateCoords(cycle)) >= 0) || 
        (opponent.history.indexOf(this.generateCoords(cycle)) >= 0)) {
      return true;
    }
  },
  
  isCollision: function(x,y) {
    coords = x + ',' + y;
    if (x < (enemy.width / 2) || 
        x > canvas.width - (enemy.width / 2) || 
        y < (enemy.height / 2) || 
        y > canvas.height - (enemy.height / 2) || 
        enemy.history.indexOf(coords) >= 0 || 
        player.history.indexOf(coords) >= 0) {
      return true;
    }    
  },
  
  generateCoords: function(cycle) {
    return cycle.x + "," + cycle.y;
  },
  
  draw: function(cycle) {
    context.fillStyle = cycle.color;
    context.beginPath();
    context.moveTo(cycle.x - (cycle.width / 2), cycle.y - (cycle.height / 2));
    context.lineTo(cycle.x + (cycle.width / 2), cycle.y - (cycle.height / 2));
    context.lineTo(cycle.x + (cycle.width / 2), cycle.y + (cycle.height / 2));
    context.lineTo(cycle.x - (cycle.width / 2), cycle.y + (cycle.height / 2));
    context.closePath();
    context.fill();
  }
  
};

inverseDirection = function() {
  switch(player.current_direction) {
    case 'up':
      return 'down';
      break;
    case 'down':
      return 'up';
      break;
    case 'right':
      return 'left';
      break;
    case 'left':
      return 'right';
      break;
  }
};

Object.prototype.getKey = function(value){
  for(var key in this){
    if(this[key] instanceof Array && this[key].indexOf(value) >= 0){
      return key;
    }
  }
  return null;
};

addEventListener("keydown", function (e) {
    lastKey = keys.getKey(e.keyCode);
    if (['up', 'down', 'left', 'right'].indexOf(lastKey) >= 0  && lastKey != inverseDirection()) {
      player.current_direction = lastKey;
    } else if (['start_game'].indexOf(lastKey) >= 0  && game.over) {
      game.start();
    }
}, false);

loop = function() {
  if (game.over === false) {
    cycle.move(player, enemy);
    cycle.draw(player);
    cycle.moveEnemy();
    cycle.draw(enemy);
  }
};

main = function() {
  game.start();
  setInterval(loop, 100);  
}();
