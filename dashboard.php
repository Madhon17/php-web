<?php
require 'connect.php';
$users = $pdo->query("SELECT uid,name FROM users")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>ESP32 RFID Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin:0; }
    .sidebar { width: 220px; background: #1e2a38; color: #fff; min-height: 100vh; transition: 0.3s; }
    .sidebar a { color: #cfd8dc; text-decoration: none; display: flex; align-items:center; padding: 12px 16px; border-radius: 8px; margin-bottom:5px; transition:0.2s; }
    .sidebar a i { margin-right:10px; }
    .sidebar a.active, .sidebar a:hover { background: #3a5068; color:#fff; }
    .content { flex-grow:1; padding:20px; transition: 0.3s; }
    .card { border-radius:1rem; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:20px; background: #fff; }
    .relay-status { font-size:2rem; font-weight:bold; color:#0d6efd; }
    .logs-table { max-height:540px; overflow-y:auto; display:block; border-radius: 0.5rem; }
    .logs-table table { width:100%; border-collapse: separate; border-spacing:0; }
    .logs-table th { position: sticky; top:0; background:#f8f9fa; z-index:2; }
    .log-success td { background-color: #d1fae5; } 
    .log-denied td { background-color: #fee2e2; } 
    .log-unknown td { background-color: #fef3c7; } 
    .table-hover tbody tr:hover { background-color: #e2e8f0; }
    .btn-modern { border-radius: 0.5rem; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    .widget-card { text-align:center; padding:15px; border-radius:1rem; color:#fff; }
    .widget-icon { font-size:2rem; margin-bottom:5px; }
    .counter { font-size:1.8rem; font-weight:bold; }
    @media(max-width:768px){ .sidebar{width:60px;} .sidebar a span{display:none;} }
  </style>
</head>
<body>
<div class="d-flex">
  <!-- Sidebar -->
  <div class="sidebar p-3 d-flex flex-column">
    <h5 class="mb-3 text-center">ESP32 Dashboard</h5>
    <a href="#" class="active" data-menu="logs"><i class="fa fa-file-alt"></i><span>Access Logs</span></a>
    <a href="#" data-menu="users"><i class="fa fa-user"></i><span>User Management</span></a>
    <a href="#" data-menu="relay"><i class="fa fa-bolt"></i><span>Relay State</span></a>
    <a href="#" data-menu="charts"><i class="fa fa-chart-line"></i><span>Charts</span></a>
  </div>

  <!-- Content -->
  <div class="content">
    <!-- Dashboard Widgets -->
    <div class="row mb-4" id="dashboardWidgets">
      <div class="col-md-3 col-6">
        <div class="card widget-card bg-primary">
          <i class="fa fa-user widget-icon"></i>
          <h5 class="counter" id="totalUsers">0</h5>
          <p>Total Users</p>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card widget-card bg-secondary">
          <i class="fa fa-database widget-icon"></i>
          <h5 class="counter" id="totalLogs">0</h5>
          <p>Total Logs (Today)</p>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card widget-card bg-success">
          <i class="fa fa-check widget-icon"></i>
          <h5 class="counter" id="grantedToday">0</h5>
          <p>Granted Today</p>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card widget-card bg-danger">
          <i class="fa fa-times widget-icon"></i>
          <h5 class="counter" id="deniedToday">0</h5>
          <p>Denied Today</p>
        </div>
      </div>
    </div>

    <!-- Access Logs -->
    <div id="logsSection">
      <h4>📜 Access Logs</h4>
      <div class="logs-table card p-2">
        <table class="table table-sm table-hover table-bordered">
          <thead class="table-light">
            <tr>
              <th>Nama</th>
              <th>UID</th>
              <th>Time</th>
              <th>Access</th>
              <th>Floor</th>
            </tr>
          </thead>
          <tbody id="logs"></tbody>
        </table>
      </div>
    </div>

    <!-- User Management -->
    <div id="usersSection" style="display:none;">
      <h4>👤 User Management</h4>
      <div class="card p-3">
        <table class="table table-hover table-bordered" id="userTable">
          <thead class="table-light">
            <tr>
              <th>UID</th>
              <th>Name</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <button id="addUserBtn" class="btn btn-success btn-sm btn-modern">Add New User</button>
      </div>
    </div>

    <!-- Relay State -->
    <div id="relaySection" style="display:none;">
      <h4>⚡ Relay State</h4>
      <div class="card text-center p-4">
        <div id="relayState" class="relay-status">Loading...</div>
        <small id="relayUpdate" class="text-muted"></small>
      </div>
    </div>

    <!-- Charts -->
    <div id="chartsSection" style="display:none;">
      <h4>📊 Access Events (Success vs Denied)</h4>
      <div class="card p-3">
        <canvas id="logChart" height="150"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
const device_token = "6c108269";
let uidToName = <?php echo json_encode($users); ?>;

// Counter animation
function animateCounter(el, newValue){
  let current = parseInt(el.textContent) || 0;
  let target = parseInt(newValue);
  let duration = 800;
  let stepTime = Math.abs(Math.floor(duration / (target - current || 1)));
  let start = current;
  let end = target;
  let step = end > start ? 1 : -1;

  if(start === end) return;

  let timer = setInterval(()=>{
    start += step;
    el.textContent = start;
    if(start === end) clearInterval(timer);
  }, stepTime);
}

// Sidebar menu click
$(".sidebar a").click(function(e){
  e.preventDefault();
  $(".sidebar a").removeClass("active");
  $(this).addClass("active");
  let menu = $(this).data("menu");
  $("#usersSection,#relaySection,#logsSection,#chartsSection").hide();
  if(menu==="users") $("#usersSection").show();
  if(menu==="relay") $("#relaySection").show();
  if(menu==="logs") $("#logsSection").show();
  if(menu==="charts") $("#chartsSection").show();
});

// Chart setup
const ctx = document.getElementById('logChart').getContext('2d');
const logChart = new Chart(ctx,{
  type:'line',
  data:{labels:[],datasets:[
    {label:'Success',data:[],borderColor:'#28a745',backgroundColor:'rgba(40,167,69,0.2)',tension:0.3,fill:true,pointRadius:3},
    {label:'Denied',data:[],borderColor:'#dc3545',backgroundColor:'rgba(220,53,69,0.2)',tension:0.3,fill:true,pointRadius:3}
  ]},
  options:{responsive:true,scales:{x:{title:{display:true,text:'Time'}},y:{beginAtZero:true,title:{display:true,text:'Count'}}}}
});

// Load logs & relay
function loadData(){
  $.getJSON("getdata.php?device_token="+device_token,function(res){
    if(!res.ok){ console.error(res); $("#relayState").text("Error").css("color","red"); return;}
    if(res.device && res.device.relay_state){
      $("#relayState").text(res.device.relay_state);
      $("#relayUpdate").text("Updated: "+(res.device.updated_at||'-'));
    } else { $("#relayState").text("Unknown"); }

    if($("#logsSection").is(":visible") || $("#dashboardWidgets").length){
      $("#logs").empty();
      let successCount=0, deniedCount=0, totalLogs=0;
      if(res.logs && res.logs.length>0){
        for(let i=res.logs.length-1;i>=0;i--){
          let l=res.logs[i];
          let t=l.created_at||'-';
          let m=l.message||'-';
          let displayName='', uid='', access='', floor='', cls='log-unknown';
          try{
            let j=JSON.parse(m);
            uid=j.uid||'';
            displayName=uidToName[uid]||uid;
            access=j.granted===true?'Granted':'Denied';
            floor=j.mask||'';
            if(j.granted===true){cls='log-success'; successCount++;}
            else if(j.granted===false){cls='log-denied'; deniedCount++;}
            totalLogs++;
          }catch(e){}
          $("#logs").prepend(`<tr class="${cls}">
            <td>${displayName}</td>
            <td>${uid}</td>
            <td>${t}</td>
            <td>${access}</td>
            <td>${floor}</td>
          </tr>`);
        }
      } else { $("#logs").append(`<tr><td colspan="5" class="text-center text-muted">No logs available</td></tr>`); }

      // Update widgets with animation
      animateCounter(document.getElementById("totalUsers"), Object.keys(uidToName).length);
      animateCounter(document.getElementById("totalLogs"), totalLogs);
      animateCounter(document.getElementById("grantedToday"), successCount);
      animateCounter(document.getElementById("deniedToday"), deniedCount);

      let now=new Date().toLocaleTimeString();
      logChart.data.labels.push(now);
      logChart.data.datasets[0].data.push(successCount);
      logChart.data.datasets[1].data.push(deniedCount);
      if(logChart.data.labels.length>10){
        logChart.data.labels.shift();
        logChart.data.datasets.forEach(ds=>ds.data.shift());
      }
      logChart.update();
    }
  });
}

// Users
function loadUsers(){
  $.getJSON('get_users.php',function(res){
    const tbody=$("#userTable tbody"); tbody.empty();
    if(res.ok && res.users){
      res.users.forEach(u=>{
        let highlight=u.name?'':'highlight';
        tbody.append(`<tr class="${highlight}">
          <td><input type="text" class="form-control form-control-sm uid-input" value="${u.uid}"></td>
          <td><input type="text" class="form-control form-control-sm name-input" value="${u.name||''}"></td>
          <td><button class="btn btn-primary btn-sm btn-modern save-btn">Save</button></td>
        </tr>`);
      });
    }
  });
}

$("#addUserBtn").click(function(){
  const tbody=$("#userTable tbody");
  let existingEmpty = tbody.find('tr.highlight').first();
  if(existingEmpty.length>0){
    existingEmpty.find('input.uid-input').val('');
    existingEmpty.find('input.name-input').val('');
    existingEmpty[0].scrollIntoView({behavior:"smooth",block:"center"});
  } else {
    tbody.prepend(`<tr class="highlight">
      <td><input type="text" class="form-control form-control-sm uid-input" value=""></td>
      <td><input type="text" class="form-control form-control-sm name-input" value=""></td>
      <td><button class="btn btn-primary btn-sm btn-modern save-btn">Save</button></td>
    </tr>`);
    tbody.find('tr.highlight').first()[0].scrollIntoView({behavior:"smooth",block:"center"});
  }
});

$(document).on('click','.save-btn',function(){
  const row=$(this).closest('tr');
  const uid=row.find('input.uid-input').val().trim();
  const name=row.find('input.name-input').val().trim();
  if(uid===''){ alert('UID cannot be empty'); return; }
  $.post('update_user.php',{uid,name},function(res){
    if(res.ok){ alert('User saved!'); loadUsers(); uidToName[uid]=name; }
    else alert('Error: '+res.err);
  },'json');
});

setInterval(loadData,4000);
loadData();
loadUsers();
</script>
</body>
</html>
