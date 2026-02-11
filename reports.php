<?php
/**
 * Admin - Reports
 */

require_once '../backend/includes/session.php';
SessionManager::requireAdmin();

// Include database connection
require_once '../backend/config/database.php';
$db = new Database();

// Get report parameters
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Set default date range based on period
if (!$start_date || !$end_date) {
    $end_date = date('Y-m-d');
    switch ($period) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            break;
        case 'day':
        default:
            $start_date = date('Y-m-d');
    }
}

// Get sales report data
$report_sql = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_customers,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as completed_revenue
              FROM orders 
              WHERE DATE(order_date) BETWEEN ? AND ?";
              
$report_stmt = $db->prepare($report_sql);
$report_stmt->bind_param("ss", $start_date, $end_date);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
$report_summary = $report_result->fetch_assoc();

// Get daily sales for chart
$daily_sql = "SELECT 
                DATE(order_date) as date,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as daily_revenue
              FROM orders 
              WHERE DATE(order_date) BETWEEN ? AND ? AND status = 'delivered'
              GROUP BY DATE(order_date)
              ORDER BY date";
              
$daily_stmt = $db->prepare($daily_sql);
$daily_stmt->bind_param("ss", $start_date, $end_date);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();

$daily_sales = [];
while ($row = $daily_result->fetch_assoc()) {
    $daily_sales[] = $row;
}

// Get top selling items
$top_items_sql = "SELECT 
                    f.food_name,
                    SUM(oi.quantity) as total_sold,
                    SUM(oi.subtotal) as total_revenue
                  FROM order_items oi
                  JOIN food_items f ON oi.food_id = f.food_id
                  JOIN orders o ON oi.order_id = o.order_id
                  WHERE DATE(o.order_date) BETWEEN ? AND ? AND o.status = 'delivered'
                  GROUP BY f.food_id
                  ORDER BY total_sold DESC
                  LIMIT 10";
                  
$top_items_stmt = $db->prepare($top_items_sql);
$top_items_stmt->bind_param("ss", $start_date, $end_date);
$top_items_stmt->execute();
$top_items_result = $top_items_stmt->get_result();

$top_items = [];
while ($row = $top_items_result->fetch_assoc()) {
    $top_items[] = $row;
}

// Get customer statistics
$customer_sql = "SELECT 
                    u.user_id,
                    u.username,
                    u.full_name,
                    COUNT(o.order_id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.order_date) as last_order
                  FROM users u
                  LEFT JOIN orders o ON u.user_id = o.user_id AND o.status = 'delivered'
                  WHERE u.role_id = 2
                  GROUP BY u.user_id
                  ORDER BY total_spent DESC
                  LIMIT 10";
                  
$customer_stmt = $db->prepare($customer_sql);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

$top_customers = [];
while ($row = $customer_result->fetch_assoc()) {
    $top_customers[] = $row;
}

// Get category performance
$category_sql = "SELECT 
                    c.category_name,
                    COUNT(oi.order_item_id) as items_sold,
                    COALESCE(SUM(oi.subtotal), 0) as category_revenue,
                    COUNT(DISTINCT o.order_id) as order_count
                  FROM categories c
                  LEFT JOIN food_items f ON c.category_id = f.category_id
                  LEFT JOIN order_items oi ON f.food_id = oi.food_id
                  LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status = 'delivered'
                  GROUP BY c.category_id
                  ORDER BY category_revenue DESC";
                  
$category_stmt = $db->prepare($category_sql);
$category_stmt->execute();
$category_result = $category_stmt->get_result();

$category_performance = [];
while ($row = $category_result->fetch_assoc()) {
    $category_performance[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FoodExpress Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-utensils"></i> FoodExpress</h2>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage-food.php">
                    <i class="fas fa-utensils"></i> Manage Food
                </a>
                <a href="manage-orders.php">
                    <i class="fas fa-shopping-cart"></i> Manage Orders
                </a>
                <a href="manage-users.php">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="reports.php" class="active">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="#" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Sales Reports</h1>
                    <p>Analytics and performance insights</p>
                </div>
                <div class="header-right">
                    <div class="admin-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
            </header>

            <!-- Date Range Selector -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Report Period</h2>
                </div>
                
                <form id="report-filter-form" class="admin-form" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="form-group" style="flex: 1;">
                        <label for="period-select">Quick Period</label>
                        <select id="period-select" name="period" class="form-control">
                            <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="custom-range-group" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; gap: 10px; flex: 2;">
                        <div style="flex: 1;">
                            <label for="start-date">Start Date</label>
                            <input type="date" id="start-date" name="start_date" class="form-control" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div style="flex: 1;">
                            <label for="end-date">End Date</label>
                            <input type="date" id="end-date" name="end_date" class="form-control" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" id="export-report-btn" class="btn btn-success">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_summary['total_orders'] ?? 0; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_summary['total_revenue'] ?? 0, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_summary['avg_order_value'] ?? 0, 2); ?></h3>
                        <p>Avg Order Value</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9b59b6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_summary['unique_customers'] ?? 0; ?></h3>
                        <p>Unique Customers</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Sales Trends</h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="sales-chart"></canvas>
                </div>
            </div>

            <!-- Top Items and Categories -->
            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>Top Selling Items</h3>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_items)): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">
                                            <p>No sales data</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['food_name']); ?></td>
                                        <td><?php echo $item['total_sold']; ?></td>
                                        <td>$<?php echo number_format($item['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Category Performance</h3>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($category_performance)): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">
                                            <p>No category data</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($category_performance as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                        <td><?php echo $category['order_count']; ?></td>
                                        <td>$<?php echo number_format($category['category_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Top Customers</h3>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_customers)): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">
                                            <p>No customer data</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['full_name'] ?: $customer['username']); ?></td>
                                        <td><?php echo $customer['order_count']; ?></td>
                                        <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Detailed Statistics</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Percentage</th>
                                <th>Insight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Completed Orders</td>
                                <td><?php echo $report_summary['completed_orders'] ?? 0; ?></td>
                                <td>
                                    <?php 
                                    $completion_rate = $report_summary['total_orders'] > 0 
                                        ? ($report_summary['completed_orders'] / $report_summary['total_orders']) * 100 
                                        : 0;
                                    echo number_format($completion_rate, 1) . '%';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($completion_rate >= 90): ?>
                                        <span class="status-badge status-active">Excellent</span>
                                    <?php elseif ($completion_rate >= 75): ?>
                                        <span class="status-badge status-confirmed">Good</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Needs Improvement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Cancelled Orders</td>
                                <td><?php echo $report_summary['cancelled_orders'] ?? 0; ?></td>
                                <td>
                                    <?php 
                                    $cancellation_rate = $report_summary['total_orders'] > 0 
                                        ? ($report_summary['cancelled_orders'] / $report_summary['total_orders']) * 100 
                                        : 0;
                                    echo number_format($cancellation_rate, 1) . '%';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($cancellation_rate <= 5): ?>
                                        <span class="status-badge status-active">Low</span>
                                    <?php elseif ($cancellation_rate <= 15): ?>
                                        <span class="status-badge status-confirmed">Acceptable</span>
                                    <?php else: ?>
                                        <span class="status-badge status-cancelled">High</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Revenue from Completed Orders</td>
                                <td>$<?php echo number_format($report_summary['completed_revenue'] ?? 0, 2); ?></td>
                                <td>
                                    <?php 
                                    $revenue_rate = $report_summary['total_revenue'] > 0 
                                        ? ($report_summary['completed_revenue'] / $report_summary['total_revenue']) * 100 
                                        : 0;
                                    echo number_format($revenue_rate, 1) . '%';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($revenue_rate >= 85): ?>
                                        <span class="status-badge status-active">High Efficiency</span>
                                    <?php elseif ($revenue_rate >= 70): ?>
                                        <span class="status-badge status-confirmed">Good</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Average Order Value</td>
                                <td>$<?php echo number_format($report_summary['avg_order_value'] ?? 0, 2); ?></td>
                                <td>-</td>
                                <td>
                                    <?php 
                                    $avg_value = $report_summary['avg_order_value'] ?? 0;
                                    if ($avg_value >= 30): ?>
                                        <span class="status-badge status-active">High</span>
                                    <?php elseif ($avg_value >= 20): ?>
                                        <span class="status-badge status-confirmed">Good</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize admin functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            initSalesChart();
            
            // Setup event listeners
            document.getElementById('period-select').addEventListener('change', function() {
                const customRangeGroup = document.getElementById('custom-range-group');
                if (this.value === 'custom') {
                    customRangeGroup.style.display = 'flex';
                } else {
                    customRangeGroup.style.display = 'none';
                }
            });
            
            document.getElementById('report-filter-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const params = new URLSearchParams(formData);
                window.location.search = params.toString();
            });
            
            document.getElementById('export-report-btn').addEventListener('click', function() {
                exportReport();
            });
        });
        
        function initSalesChart() {
            const ctx = document.getElementById('sales-chart').getContext('2d');
            
            // Prepare data from PHP
            const dailySales = <?php echo json_encode($daily_sales); ?>;
            
            const labels = dailySales.map(sale => {
                const date = new Date(sale.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const revenueData = dailySales.map(sale => parseFloat(sale.daily_revenue) || 0);
            const orderData = dailySales.map(sale => parseInt(sale.order_count) || 0);
            
            // Create chart
            window.salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Daily Revenue',
                            data: revenueData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: orderData,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    stacked: false,
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += '$' + context.parsed.y.toFixed(2);
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function exportReport() {
            // Create a data object for export
            const reportData = {
                period: document.getElementById('period-select').value,
                start_date: document.getElementById('start-date').value || '<?php echo $start_date; ?>',
                end_date: document.getElementById('end-date').value || '<?php echo $end_date; ?>',
                summary: <?php echo json_encode($report_summary); ?>,
                daily_sales: <?php echo json_encode($daily_sales); ?>,
                top_items: <?php echo json_encode($top_items); ?>,
                top_customers: <?php echo json_encode($top_customers); ?>,
                category_performance: <?php echo json_encode($category_performance); ?>,
                generated_at: new Date().toISOString()
            };
            
            // Convert to JSON and create download link
            const dataStr = JSON.stringify(reportData, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = foodexpress_report_${new Date().toISOString().split('T')[0]}.json;
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
            
            showNotification('Report exported successfully', 'success');
        }
        
        // Update chart when data changes
        function updateChart() {
            if (window.salesChart) {
                // In a real implementation, this would fetch new data
                showNotification('Chart data would update here', 'info');
            }
        }
    </script>
</body>
</html>