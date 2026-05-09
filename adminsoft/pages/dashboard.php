<?php
// requests.php - Improved Dashboard Version
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get dashboard stats
$stats = [];
try {
    // Total Active Residents
    $query = "SELECT COUNT(*) as count FROM resident WHERE STATUS='active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_residents'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Current Pending Requests
    $query = "SELECT COUNT(*) as count FROM request WHERE status='pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Completed Requests
    $query = "SELECT COUNT(*) as count FROM request WHERE status='completed'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['completed_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Document Type Stats (approved + completed) - Only get documents with counts > 0
    $query = "SELECT dt.name, COUNT(*) as count 
              FROM request r 
              JOIN document_type dt ON r.document_type_id = dt.type_id 
              WHERE r.status IN ('approved', 'completed') 
              GROUP BY dt.name
              HAVING COUNT(*) > 0
              ORDER BY COUNT(*) DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['document_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Pending Requests (5 most recent)
    $query = "SELECT r.request_code, r.resident_name, r.status, dt.name as document_type, 
                     DATE_FORMAT(r.request_date, '%M %d, %Y') as request_date
              FROM request r 
              JOIN document_type dt ON r.document_type_id = dt.type_id 
              WHERE r.status = 'pending'
              ORDER BY r.request_date DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['recent_pending'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = [
        'active_residents' => 0,
        'pending_requests' => 0,
        'completed_requests' => 0,
        'document_types' => [],
        'recent_pending' => []
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requests Dashboard - BarangayHub</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c5aa0;
            --primary-light: #3a6bc3;
            --primary-dark: #1e3d72;
            --secondary: #d4af37;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --gradient-primary: linear-gradient(135deg, #2c5aa0 0%, #3a6bc3 100%);
            --gradient-secondary: linear-gradient(135deg, #d4af37 0%, #e6c34a 100%);
            --gradient-success: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
            --gradient-warning: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%);
            --gradient-light: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.15);
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Dashboard Layout */
        .dashboard-container {
            max-width: 100%;
            margin: 0;
            padding: 30px;
            width: 100%;
            min-height: calc(100vh - 120px);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
            overflow-x: hidden;
        }

        .dashboard-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 300px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            clip-path: polygon(0 0, 100% 0, 100% 70%, 0 100%);
            z-index: 0;
        }

        /* Stats Section */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 35px;
            position: relative;
            z-index: 1;
        }
        
        .stat-card {
            background: var(--gradient-light);
            padding: 30px 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            opacity: 0.8;
        }

        .stat-card:nth-child(2)::before {
            background: var(--gradient-warning);
        }

        .stat-card:nth-child(3)::before {
            background: var(--gradient-success);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: var(--gradient-warning);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: var(--gradient-success);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-icon i {
            font-size: 28px;
            color: white;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card:nth-child(2) .stat-value {
            background: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card:nth-child(3) .stat-value {
            background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Dashboard Content Grid */
        .dashboard-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .chart-container, .recent-container {
            background: var(--gradient-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            min-height: 550px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .chart-container:hover, .recent-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .chart-header, .recent-header {
            padding: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(15px);
        }
        
        .chart-title, .recent-title {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            position: relative;
            display: inline-block;
        }

        .chart-title::after, .recent-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 2px;
            transition: var(--transition);
        }

        .chart-container:hover .chart-title::after,
        .recent-container:hover .recent-title::after {
            width: 60px;
        }
        
        .chart-body {
            padding: 20px;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 450px;
        }
        
        .chart-wrapper {
            width: 100%;
            height: 100%;
            max-width: 550px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Chart Legend Custom Styling */
        .chart-legend {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .legend-item:hover {
            background: rgba(44, 90, 160, 0.1);
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            flex-shrink: 0;
        }
        
        .legend-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
        }
        
        /* Recent Requests Table */
        .recent-body {
            padding: 0;
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .recent-table {
            width: 100%;
            border-collapse: collapse;
            flex: 1;
        }
        
        .recent-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
            color: var(--primary);
            font-size: 1.2rem;
            padding: 20px 25px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        
        .recent-table td {
            padding: 18px 25px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
            font-weight: 500;
            font-size: 1.2rem;
        }
        
        .recent-table tbody tr {
            background: white;
            transition: var(--transition);
        }
        
        .recent-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.1);
        }
        
        /* Status Badges */
        .status {
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 1.05rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .status-pending { 
            background: var(--gradient-warning);
            color: #856404; 
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-pending:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--gray);
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: var(--primary);
        }
        
        .empty-state h4 {
            font-size: 1.7rem;
            margin-bottom: 10px;
            color: var(--primary);
            font-weight: 700;
        }
        
        .empty-state p {
            font-size: 1.2rem;
            opacity: 0.7;
        }

        /* Request Code Styling */
        .request-code {
            font-weight: 700;
            color: var(--primary);
            background: rgba(44, 90, 160, 0.1);
            padding: 6px 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container {
                padding: 25px;
            }
            
            .stats-container {
                gap: 20px;
            }
        }
        
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px 15px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 25px 20px;
            }
            
            .stat-value {
                font-size: 36px;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
            }

            .stat-icon i {
                font-size: 24px;
            }
            
            .chart-header, .recent-header {
                padding: 20px;
            }
            
            .chart-title, .recent-title {
                font-size: 1.4rem;
            }
            
            .recent-table th,
            .recent-table td {
                padding: 15px 18px;
                font-size: 1.1rem;
            }

            .chart-body {
                height: 400px;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 15px 10px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .stat-value {
                font-size: 32px;
            }
            
            .chart-title, .recent-title {
                font-size: 1.3rem;
            }
            
            .recent-table {
                font-size: 1rem;
            }
            
            .recent-table th,
            .recent-table td {
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Active Residents</div>
                <div class="stat-value"><?php echo $stats['active_residents']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Completed Requests</div>
                <div class="stat-value"><?php echo $stats['completed_requests']; ?></div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Document Types Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Document Types Distribution</h3>
                </div>
                <div class="chart-body">
                    <div class="chart-wrapper">
                        <canvas id="documentChart"></canvas>
                    </div>
                    <!-- Custom Legend -->
                    <div id="chartLegend" class="chart-legend"></div>
                </div>
            </div>
            
            <!-- Recent Pending Requests -->
            <div class="recent-container">
                <div class="recent-header">
                    <h3 class="recent-title">Recent Pending Requests</h3>
                </div>
                <div class="recent-body">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Request Code</th>
                                <th>Resident Name</th>
                                <th>Document Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stats['recent_pending'])): ?>
                                <?php foreach ($stats['recent_pending'] as $request): ?>
                                <tr>
                                    <td><span class="request-code"><?php echo htmlspecialchars($request['request_code']); ?></span></td>
                                    <td style="font-weight: 600; color: #495057;"><?php echo htmlspecialchars($request['resident_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td>
                                        <span class="status status-pending">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <h4>No Pending Requests</h4>
                                            <p>All requests have been processed.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Document Types Chart
        const documentCtx = document.getElementById('documentChart').getContext('2d');
        const legendContainer = document.getElementById('chartLegend');
        
        // Prepare chart data from PHP
        const documentLabels = <?php echo json_encode(array_column($stats['document_types'], 'name')); ?>;
        const documentData = <?php echo json_encode(array_column($stats['document_types'], 'count')); ?>;
        
        console.log('Document Labels:', documentLabels);
        console.log('Document Data:', documentData);
        
        // Calculate total for percentage calculation
        const totalDocuments = documentData.reduce((sum, value) => sum + parseInt(value), 0);
        console.log('Total Documents:', totalDocuments);
        
        // Enhanced colors for the chart
        const chartColors = [
            'rgba(44, 90, 160, 0.9)',
            'rgba(255, 193, 7, 0.9)',
            'rgba(40, 167, 69, 0.9)',
            'rgba(220, 53, 69, 0.9)',
            'rgba(108, 117, 125, 0.9)',
            'rgba(23, 162, 184, 0.9)',
            'rgba(111, 66, 193, 0.9)',
            'rgba(253, 126, 20, 0.9)'
        ];
        
        const borderColors = [
            'rgba(44, 90, 160, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(40, 167, 69, 1)',
            'rgba(220, 53, 69, 1)',
            'rgba(108, 117, 125, 1)',
            'rgba(23, 162, 184, 1)',
            'rgba(111, 66, 193, 1)',
            'rgba(253, 126, 20, 1)'
        ];
        
        // Create custom legend
        function createCustomLegend() {
            legendContainer.innerHTML = '';
            documentLabels.forEach((label, index) => {
                const value = documentData[index];
                const percentage = totalDocuments > 0 ? ((parseInt(value) / totalDocuments) * 100).toFixed(1) : 0;
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${chartColors[index]}"></div>
                    <div class="legend-text">${label}: ${value} (${percentage}%)</div>
                `;
                legendContainer.appendChild(legendItem);
            });
        }
        
        // Create the chart with improved configuration
        const documentChart = new Chart(documentCtx, {
            type: 'doughnut',
            data: {
                labels: documentLabels, // Clean labels without percentages
                datasets: [{
                    data: documentData,
                    backgroundColor: chartColors,
                    borderColor: borderColors,
                    borderWidth: 3,
                    hoverBorderWidth: 4,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false, // Hide default legend
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#2c5aa0',
                        bodyColor: '#495057',
                        borderColor: 'rgba(44, 90, 160, 0.2)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        titleFont: {
                            size: 16,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 16
                        },
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const percentage = totalDocuments > 0 ? ((parseInt(value) / totalDocuments) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '35%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1200,
                    easing: 'easeOutQuart',
                    onComplete: function() {
                        createCustomLegend();
                    }
                },
                layout: {
                    padding: {
                        top: 20,
                        bottom: 20,
                        left: 20,
                        right: 20
                    }
                }
            }
        });

        // Create legend initially
        createCustomLegend();
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>