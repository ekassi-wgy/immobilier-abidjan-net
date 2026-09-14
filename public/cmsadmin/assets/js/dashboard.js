/**
 * cmsadmin — graphique d'audience du tableau de bord (Chart.js)
 */
(function () {
  'use strict';

  var canvas = document.getElementById('im-audience-chart');
  var source = document.getElementById('im-audience-data');
  if (!canvas || !source || !window.Chart) {
    return;
  }

  var data = JSON.parse(source.textContent);
  var css = getComputedStyle(document.documentElement);
  var color = function (name) { return css.getPropertyValue(name).trim(); };
  var number = new Intl.NumberFormat('fr-FR');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  Chart.defaults.font.family = color('--im-font');
  Chart.defaults.font.size = 12;
  Chart.defaults.color = color('--im-muted');

  var ctx = canvas.getContext('2d');
  var gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentNode.clientHeight || 300);
  gradient.addColorStop(0, 'rgba(38, 80, 219, 0.14)');
  gradient.addColorStop(1, 'rgba(38, 80, 219, 0)');

  new Chart(ctx, {
    data: {
      labels: data.labels,
      datasets: [
        {
          type: 'line',
          label: 'Vues',
          data: data.views,
          yAxisID: 'views',
          borderColor: color('--im-blue'),
          borderWidth: 2,
          backgroundColor: gradient,
          fill: true,
          tension: 0.35,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBackgroundColor: color('--im-blue'),
          order: 1
        },
        {
          type: 'bar',
          label: 'Contacts',
          data: data.leads,
          yAxisID: 'leads',
          backgroundColor: color('--im-navy'),
          borderRadius: 3,
          barPercentage: 0.45,
          categoryPercentage: 0.9,
          order: 2
        }
      ]
    },
    options: {
      maintainAspectRatio: false,
      animation: reduceMotion ? false : { duration: 500 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: color('--im-ink'),
          padding: 12,
          cornerRadius: 8,
          boxPadding: 4,
          titleFont: { weight: '600' },
          callbacks: {
            label: function (item) { return ' ' + item.dataset.label + ' : ' + number.format(item.parsed.y); }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { maxRotation: 0, autoSkipPadding: 24 }
        },
        views: {
          position: 'left',
          beginAtZero: true,
          border: { display: false },
          grid: { color: color('--im-line'), drawTicks: false },
          ticks: { padding: 8, maxTicksLimit: 5, callback: function (v) { return number.format(v); } }
        },
        leads: {
          position: 'right',
          beginAtZero: true,
          suggestedMax: Math.max.apply(null, data.leads) * 3,
          border: { display: false },
          grid: { display: false },
          ticks: { padding: 8, maxTicksLimit: 5, precision: 0 }
        }
      }
    }
  });
})();
