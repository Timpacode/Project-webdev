// js/dashboard.js
// Fetches and renders dashboard KPIs, charts, and tables.
// Notes: Pie shows only 3 document types (approved+completed).
// Pending table shows only 3 most recent items (handled server-side).
// No 'Total Requests' label/slice is generated anywhere.

document.addEventListener('DOMContentLoaded', () => {
  fetchDashboard();
});

let weeklyChart, monthlyChart;

function fetchDashboard() {
  fetch('../api/get_dashboard_stats.php').then(r => {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(json => {
    if (!json.success) throw new Error(json.message || 'Failed');
    renderKpis(json.kpis);
    renderWeekly(json.weekly);
    renderMonthly(json.monthly);
    renderRecent(json.recent);
    renderPendingRequests(json.pending_requests);
  }).catch(err => {
    console.error(err);
    // fallback
    renderKpis({total_residents:0,documents_issued:0,pending_requests:0});
    renderWeekly({labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data:[0,0,0,0,0,0,0]});
    renderMonthly({labels:['Barangay Clearance','Certificate of Residency','Certificate of Indigency'], data:[0,0,0]});
    renderRecent([]);
    renderPendingRequests([]);
  });
}

function renderKpis(k) {
  setText('kpi-total-residents', k.total_residents);
  setText('kpi-docs-issued', k.documents_issued);
  setText('kpi-pending', k.pending_requests);
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = (val ?? 0).toLocaleString();
}

function renderWeekly(w) {
  const ctx = document.getElementById('weeklyChart');
  if (!ctx) return;
  weeklyChart && weeklyChart.destroy();
  weeklyChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: w.labels,
      datasets: [{
        label: 'Requests',
        data: w.data,
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      },
      plugins: { legend: { display: false } }
    }
  });
}

function renderMonthly(m) {
  const ctx = document.getElementById('monthlyChart');
  if (!ctx) return;
  monthlyChart && monthlyChart.destroy();
  monthlyChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: m.labels,
      datasets: [{
        data: m.data
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { 
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function(context) {
              const label = context.label || '';
              const value = context.raw || 0;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
              return `${label}: ${value} (${percentage}%)`;
            }
          }
        }
      }
    }
  });
}

function renderRecent(rows) {
  const tbody = document.querySelector('#recent-requests tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  if (!rows || !rows.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No recent requests</td></tr>';
    return;
  }
  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(r.request_code)}</td>
      <td>${escapeHtml(r.resident_name)}</td>
      <td>${escapeHtml(r.document_type)}</td>
      <td><span class="status status-${escapeHtml(r.status)}">${escapeHtml(r.status)}</span></td>
      <td>${escapeHtml(r.d)}</td>`;
    tbody.appendChild(tr);
  });
}

function renderPendingRequests(rows) {
  const tbody = document.querySelector('#pending-requests tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  if (!rows || !rows.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No pending requests</td></tr>';
    return;
  }
  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(r.request_code)}</td>
      <td>${escapeHtml(r.resident_name)}</td>
      <td>${escapeHtml(r.document_type)}</td>
      <td><span class="urgency-${escapeHtml(r.urgency_level)}">${escapeHtml(r.urgency_level)}</span></td>
      <td>${escapeHtml(r.request_date)}</td>`;
    tbody.appendChild(tr);
  });
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
