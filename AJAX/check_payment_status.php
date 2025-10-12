<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type
header('Content-Type: application/json');

try {
    // Check session
    session_start();
    
    echo "/* Debug: Session started */\n";
    
    // Check if client is authenticated
    if (!isset($_SESSION['client_id']) && !isset($_POST['client_id'])) {
        throw new Exception('Not authenticated - No client ID found');
    }

    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : $_SESSION['client_id'];
    echo "/* Debug: Client ID = " . $client_id . " */\n";

    // Check database connection
    require 'database/database.php';
    $db = new Database();
    
    if (!$db->pdo) {
        throw new Exception('Database connection failed');
    }
    echo "/* Debug: Database connected */\n";

    // Test query
    $sql = "SELECT 1 as test";
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute();
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "/* Debug: Test query successful */\n";

    // Your actual query
    $sql = "SELECT i.Invoice_ID, i.InvoiceDate, i.EndDate, i.Status, i.Flow_Status, 
                   s.Name as SpaceName,
                   DATE_FORMAT(i.InvoiceDate, '%M %Y') as PaidMonth,
                   DATE_FORMAT(i.EndDate, '%M %d, %Y') as NextDueDate
            FROM invoice i
            INNER JOIN space s ON i.Space_ID = s.Space_ID
            WHERE i.Client_ID = ? 
            AND i.Status = 'paid' 
            AND i.Flow_Status = 'done'
            AND i.InvoiceDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY i.InvoiceDate DESC 
            LIMIT 1";
    
    echo "/* Debug: Preparing main query */\n";
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $recent_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "/* Debug: Query executed */\n";

    if ($recent_payment) {
        echo "/* Debug: Payment found - " . json_encode($recent_payment) . " */\n";
        
        $popup_shown_key = 'payment_popup_shown_' . $recent_payment['Invoice_ID'];
        
        if (!isset($_SESSION[$popup_shown_key])) {
            $_SESSION[$popup_shown_key] = true;
            
            $response = [
                'success' => true,
                'show_popup' => true,
                'paid_month' => $recent_payment['PaidMonth'],
                'next_due_date' => $recent_payment['NextDueDate'],
                'space_name' => $recent_payment['SpaceName']
            ];
        } else {
            $response = [
                'success' => true,
                'show_popup' => false,
                'message' => 'Popup already shown'
            ];
        }
    } else {
        echo "/* Debug: No recent payments found */\n";
        $response = [
            'success' => true,
            'show_popup' => false,
            'message' => 'No recent payments found'
        ];
    }
    
    // Clear any previous output and send JSON
    if (ob_get_length()) ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log("Payment status check error: " . $e->getMessage());
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode($error_response);
}
?>