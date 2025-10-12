
<?php

class Database {

    private $host = "mysql.hostinger.com";
    private $dbname = "u321173822_asrp"; // UPDATED DATABASE NAME
    private $user = "u321173822_alicia";
    private $pass = "Pogilameg@10";
    private $pdo;

    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->pdo->exec("SET time_zone = '+08:00'");

        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

       public function getRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- Private Helper Methods ---
    // CHANGED FROM private TO protected FOR EXTENSIBILITY, BUT NOT public!
    protected function runQuery($sql, $params = [], $fetchAll = false) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
        } catch (PDOException $e) { return false; }
    }

public function executeStatement($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) { return false; }
    }

    protected function insertAndGetId($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) { return false; }
    }

    public function invoiceExists($client_id, $due_date) {
        $sql = "SELECT 1 FROM invoice WHERE Client_ID = ? AND InvoiceDate = ?";
        $result = $this->runQuery($sql, [$client_id, $due_date]);
        return (bool)$result;
    }


    
    public function getSpaceByName($name) {
    $sql = "SELECT * FROM space WHERE Name = ? ORDER BY Space_ID DESC LIMIT 1";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getSpaceByName Error: " . $e->getMessage());
        return null;
    }
}


    
public function executeQuery($sql, $params = []) {
    try {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return [];
    }
}





public function getOccupancyData($startDate, $endDate) {
    try {
        $monthDays = date('t', strtotime($startDate));
        
        $sql = "SELECT 
                    s.Space_ID, s.Name, s.Price, st.SpaceTypeName,
                    c.Client_fn, c.Client_ln,
                    cs.active,
                    CASE WHEN cs.active = 1 THEN 'Occupied' ELSE 'Vacant' END as Status,
                    DATEDIFF(LEAST(?, i.EndDate), GREATEST(?, i.InvoiceDate)) as occupied_days,
                    ? as month_days,
                    (DATEDIFF(LEAST(?, i.EndDate), GREATEST(?, i.InvoiceDate)) / ? * 100) as utilization_rate,
                    i.InvoiceTotal as revenue
                FROM space s
                LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
                LEFT JOIN clientspace cs ON s.Space_ID = cs.Space_ID AND cs.active = 1
                LEFT JOIN client c ON cs.Client_ID = c.Client_ID
                LEFT JOIN invoice i ON s.Space_ID = i.Space_ID 
                    AND i.Status = 'paid' 
                    AND i.InvoiceDate BETWEEN ? AND ?
                WHERE s.Flow_Status = 'old'
                ORDER BY s.SpaceType_ID, s.Name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $endDate, $startDate, $monthDays,
            $endDate, $startDate, $monthDays,
            $startDate, $endDate
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting occupancy data: " . $e->getMessage());
        return [];
    }
}

public function getFinancialSummary($startDate, $endDate) {
    try {
        $sql = "SELECT 
                    COUNT(CASE WHEN Status = 'unpaid' AND EndDate < CURDATE() THEN 1 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN st.SpaceTypeName = 'Space' AND Status = 'paid' THEN i.InvoiceTotal ELSE 0 END), 0) as space_revenue,
                    COALESCE(SUM(CASE WHEN st.SpaceTypeName = 'Apartment' AND Status = 'paid' THEN i.InvoiceTotal ELSE 0 END), 0) as apartment_revenue,
                    COALESCE(SUM(CASE WHEN Status = 'paid' THEN i.InvoiceTotal ELSE 0 END), 0) as total_revenue
                FROM invoice i
                LEFT JOIN space s ON i.Space_ID = s.Space_ID
                LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
                WHERE i.InvoiceDate BETWEEN ? AND ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting financial summary: " . $e->getMessage());
        return [];
    }
}




public function getUserByEmail($email) {
    $stmt = $this->pdo->prepare("SELECT * FROM client WHERE LOWER(Client_Email) = LOWER(?)");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}



public function updatePasswordByEmail($email, $hashedPassword) {
    $stmt = $this->pdo->prepare("UPDATE client SET C_password = ? WHERE Client_Email = ?");
    return $stmt->execute([$hashedPassword, $email]);
}






public function updateUnitPhotos($space_id, $client_id, $json_photos) {
    try {
        $sql = "UPDATE clientspace SET BusinessPhoto = ? WHERE Space_ID = ? AND Client_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$json_photos, $space_id, $client_id]);
        
        if (!$result) {
            error_log("updateUnitPhotos failed: " . print_r([$sql, $json_photos, $space_id, $client_id], true));
        }
        return $result;
    } catch (PDOException $e) {
        error_log("updateUnitPhotos PDOException: " . $e->getMessage());
        return false;
    }
}



public function getUnitPhotosForClient($client_id) {
    try {
        $sql = "SELECT Space_ID, BusinessPhoto
                FROM clientspace
                WHERE Client_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$client_id]);
        $photos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Decode the JSON array from BusinessPhoto column
            $photo_array = !empty($row['BusinessPhoto']) ? json_decode($row['BusinessPhoto'], true) : [];
            
            // Ensure it's a valid array
            $photos[$row['Space_ID']] = is_array($photo_array) ? $photo_array : [];
        }
        return $photos;
    } catch (PDOException $e) {
        error_log("getUnitPhotosForClient PDOException: " . $e->getMessage());
        return [];
    }
}


public function getAllUnitPhotosForUnits($unit_ids) {
    if (empty($unit_ids)) return [];
    
    // Prepare placeholders for array of unit IDs
    $placeholders = implode(',', array_fill(0, count($unit_ids), '?'));
    $sql = "SELECT Space_ID, BusinessPhoto 
            FROM clientspace 
            WHERE Space_ID IN ($placeholders)";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($unit_ids);
    
    $photos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Decode the JSON array from BusinessPhoto column
        $photo_array = !empty($row['BusinessPhoto']) ? json_decode($row['BusinessPhoto'], true) : [];
        
        // Ensure it's a valid array
        $photos[$row['Space_ID']] = is_array($photo_array) ? $photo_array : [];
    }
    return $photos;
}

// In the client dashboard, replace the addClientPhotoToHistory function with:
function addClientPhotoToHistory($db, $space_id, $photo_path, $action, $description = null) {
    // Use negative client_id to distinguish client actions from admin actions
    $client_id = -$_SESSION['client_id'];
    
    if (method_exists($db, 'addPhotoToHistory')) {
        // Remove the description parameter since your method doesn't accept it
        return $db->addPhotoToHistory($space_id, $photo_path, $action, null, $client_id);
    }
    
    // Fallback: direct SQL if method doesn't exist
    try {
        $sql = "INSERT INTO photo_history (Space_ID, Photo_Path, Action, Previous_Photo_Path, Action_By, Status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $status = ($action === 'deleted') ? 'inactive' : 'active';
        $stmt = $db->pdo->prepare($sql);
        return $stmt->execute([$space_id, $photo_path, $action, null, $client_id, $status]);
    } catch (PDOException $e) {
        error_log("addClientPhotoToHistory Error: " . $e->getMessage());
        return false;
    }
}



public function getInvoiceChatMessagesForClient($invoice_id) {
    $sql = "SELECT ic.*, 
               CASE 
                   WHEN ic.Sender_Type='admin' THEN 'Admin'
                   WHEN ic.Sender_Type='system' THEN 'System'
                   ELSE CONCAT(c.Client_fn, ' ', c.Client_ln) 
               END AS SenderName
        FROM invoice_chat ic
        LEFT JOIN invoice i ON ic.Invoice_ID = i.Invoice_ID
        LEFT JOIN client c ON i.Client_ID = c.Client_ID
        WHERE ic.Invoice_ID = ?
        ORDER BY ic.Created_At ASC";
    return $this->runQuery($sql, [$invoice_id], true);
}

public function nukeClient($client_id) {
    $pdo = $this->pdo;
    $pdo->beginTransaction();
    try {
        // Get all invoice IDs for this client
        $stmt = $pdo->prepare("SELECT Invoice_ID FROM invoice WHERE Client_ID = ?");
        $stmt->execute([$client_id]);
        $invoice_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 1. Delete payment history for those invoices
        if ($invoice_ids) {
            $in_ids = implode(",", array_fill(0, count($invoice_ids), "?"));
            $pdo->prepare("DELETE FROM paymenthistory WHERE Invoice_ID IN ($in_ids)")->execute($invoice_ids);
            $pdo->prepare("DELETE FROM transaction WHERE Invoice_ID IN ($in_ids)")->execute($invoice_ids);
            $pdo->prepare("DELETE FROM invoice_chat WHERE Invoice_ID IN ($in_ids)")->execute($invoice_ids);
            $pdo->prepare("DELETE FROM invoice_chat_seen WHERE Invoice_ID IN ($in_ids)")->execute($invoice_ids);
            $pdo->prepare("DELETE FROM clientfeedback WHERE CS_ID IN ($in_ids)")->execute($invoice_ids);
        }

        // 2. Delete all chats seen by this client as well
        $pdo->prepare("DELETE FROM invoice_chat_seen WHERE Client_ID = ?")->execute([$client_id]);

        // 3. Delete all invoices
        if ($invoice_ids) {
            $in_ids = implode(",", array_fill(0, count($invoice_ids), "?"));
            $pdo->prepare("DELETE FROM invoice WHERE Invoice_ID IN ($in_ids)")->execute($invoice_ids);
        }

        // 4. Delete clientspace (will free spaces for others)
        $pdo->prepare("DELETE FROM clientspace WHERE Client_ID = ?")->execute([$client_id]);

        // 5. Set any spaceavailability to Available for units that were occupied by this client
        $spaces = $pdo->prepare("SELECT Space_ID FROM clientspace WHERE Client_ID = ?");
        $spaces->execute([$client_id]);
        $space_ids = $spaces->fetchAll(PDO::FETCH_COLUMN);
        if ($space_ids) {
            foreach ($space_ids as $sid) {
                $pdo->prepare("UPDATE spaceavailability SET Status = 'Available' WHERE Space_ID = ? AND Status = 'Occupied'")->execute([$sid]);
                // Also set Flow_Status to 'new' in the space table
                $pdo->prepare("UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?")->execute([$sid]);
            }
        }

        // 6. Delete maintenance request history for this client's requests
        $reqs = $pdo->prepare("SELECT Request_ID FROM maintenancerequest WHERE Client_ID = ?");
        $reqs->execute([$client_id]);
        $request_ids = $reqs->fetchAll(PDO::FETCH_COLUMN);
        if ($request_ids) {
            $in_rids = implode(",", array_fill(0, count($request_ids), "?"));
            $pdo->prepare("DELETE FROM maintenancerequeststatushistory WHERE Request_ID IN ($in_rids)")->execute($request_ids);
        }

        // 7. Delete maintenance requests
        $pdo->prepare("DELETE FROM maintenancerequest WHERE Client_ID = ?")->execute([$client_id]);

        // 8. Delete rental requests
        $pdo->prepare("DELETE FROM rentalrequest WHERE Client_ID = ?")->execute([$client_id]);

        // 9. Finally delete client
        $pdo->prepare("DELETE FROM client WHERE Client_ID = ?")->execute([$client_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}




public function setUnitAvailable($space_id) {
    $this->executeStatement(
        "UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?", 
        [$space_id]
    );
    // Debug log
    $status = $this->runQuery("SELECT Flow_Status FROM space WHERE Space_ID = ?", [$space_id]);
    error_log("setUnitAvailable: After update, Space_ID $space_id Flow_Status: " . print_r($status, true));
    // ...rest of your code
    $this->executeStatement(
        "UPDATE spaceavailability SET Status = 'Available', EndDate = CURDATE() 
         WHERE Space_ID = ? AND Status = 'Occupied' AND EndDate >= CURDATE()", 
        [$space_id]
    );
    $exists = $this->runQuery(
        "SELECT 1 FROM spaceavailability WHERE Space_ID = ? AND Status = 'Available'", 
        [$space_id]
    );
    if (!$exists) {
        $this->executeStatement(
            "INSERT INTO spaceavailability (Space_ID, Status) VALUES (?, 'Available')", 
            [$space_id]
        );
    }
}

public function getInvoicesByFlowStatus($status = 'new') {
    $sql = "SELECT i.*, c.Client_fn, c.Client_ln, s.Name AS UnitName
            FROM invoice i
            LEFT JOIN client c ON i.Client_ID = c.Client_ID
            LEFT JOIN space s ON i.Space_ID = s.Space_ID
            WHERE i.Flow_Status = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateInvoiceFlowStatus($invoice_id, $status) {
    $stmt = $this->pdo->prepare("UPDATE invoice SET Flow_Status = ? WHERE Invoice_ID = ?");
    return $stmt->execute([$status, $invoice_id]);
}

// Get all invoices by flow status, e.g. 'new'

public function sendInvoiceChat($invoice_id, $sender_type, $sender_id, $message, $image_path = null) {
    // Set unread/read flags
    $is_read_admin = ($sender_type === 'admin' || $sender_type === 'system') ? 1 : 0;
    $is_read_client = ($sender_type === 'client') ? 1 : 0;
    $sql = "INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At, is_read_admin, is_read_client)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
    return $this->executeStatement($sql, [$invoice_id, $sender_type, $sender_id, $message, $image_path, $is_read_admin, $is_read_client]);
}

public function getInvoiceHistoryForClient($client_id) {
    $sql = "SELECT * FROM invoice WHERE Client_ID = ?";
    return $this->runQuery($sql, [$client_id], true);
}


public function runQueryAll($query, $params = []) {
    $stmt = $this->pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    

    // --- Payment History Methods ---
    public function addPaymentHistory($invoice_id, $amount, $method) {
        $sql = "INSERT INTO paymenthistory (Invoice_ID, PaymentDate, Amount, Method) VALUES (?, CURDATE(), ?, ?)";
        return $this->executeStatement($sql, [$invoice_id, $amount, $method]);
    }

    public function getPaymentHistoryByInvoice($invoice_id) {
        $sql = "SELECT * FROM paymenthistory WHERE Invoice_ID = ? ORDER BY PaymentDate DESC";
        return $this->runQuery($sql, [$invoice_id], true);
    }

    // --- Client Authentication & Registration ---
    public function getClientByUsername($username) {
        $sql = "SELECT Client_ID, Client_fn, C_username, C_password, Status FROM client WHERE C_username = ?";
        return $this->runQuery($sql, [$username]);
    }

    public function setClientStatus($client_id, $status) {
        $sql = "UPDATE client SET Status = ? WHERE Client_ID = ?";
        return $this->executeStatement($sql, [$status, $client_id]);
    }

    public function registerClient($fname, $lname, $email, $phone, $username, $hashed_password) {
        $sql = "INSERT INTO client (Client_fn, Client_ln, Client_Email, Client_Phone, C_username, C_password, Status) VALUES (?, ?, ?, ?, ?, ?, 'Active')";
        return $this->executeStatement($sql, [$fname, $lname, $email, $phone, $username, $hashed_password]);
    }

    // --- Homepage & Dashboard Data ---
    public function getClientDetails($client_id) {
        $sql = "SELECT Client_fn, Client_ln, C_username FROM client WHERE Client_ID = ?";
        return $this->runQuery($sql, [$client_id]);
    }

    public function getClientStatus($client_id) {
        $sql = "SELECT Status FROM client WHERE Client_ID = ?";
        return $this->runQuery($sql, [$client_id]);
    }

    public function getClientFullDetails($client_id) {
        $sql = "SELECT Client_fn, Client_ln, C_username, Status FROM client WHERE Client_ID = ?";
        return $this->runQuery($sql, [$client_id]);
    }

  public function getClientsWithLastInvoice() {
        $sql = "SELECT i.Invoice_ID, c.Client_ID, c.Client_fn, c.Client_ln, s.Name AS UnitName,
                       i.InvoiceDate, i.Status,
                       r.EndDate
                FROM invoice i
                LEFT JOIN client c ON i.Client_ID = c.Client_ID
                LEFT JOIN space s ON i.Space_ID = s.Space_ID
                LEFT JOIN rentalrequest r ON r.Client_ID = i.Client_ID AND r.Space_ID = i.Space_ID AND r.Status = 'Accepted'
                WHERE i.Status = 'unpaid'
                ORDER BY r.EndDate DESC, i.InvoiceDate DESC";
        return $this->runQuery($sql, [], true);
    }
    

public function getRentedUnits($client_id) {
    $sql = "SELECT s.Space_ID, s.Name, s.Price, st.SpaceTypeName, s.Street, s.Brgy, s.City,
                   sa.StartDate, sa.EndDate
            FROM clientspace cs
            JOIN space s ON cs.Space_ID = s.Space_ID
            LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
            LEFT JOIN spaceavailability sa ON sa.Space_ID = s.Space_ID
                AND sa.Status = 'Occupied'
                AND sa.EndDate = (
                    SELECT MAX(sa2.EndDate) FROM spaceavailability sa2
                    WHERE sa2.Space_ID = s.Space_ID AND sa2.Status = 'Occupied'
                )
            JOIN (
                SELECT inv1.*
                FROM invoice inv1
                INNER JOIN (
                    SELECT Client_ID, Space_ID, MAX(Created_At) AS max_created
                    FROM invoice
                    GROUP BY Client_ID, Space_ID
                ) inv2 ON inv1.Client_ID = inv2.Client_ID AND inv1.Space_ID = inv2.Space_ID AND inv1.Created_At = inv2.max_created
            ) i ON i.Client_ID = cs.Client_ID AND i.Space_ID = cs.Space_ID
            WHERE cs.Client_ID = ? 
              AND i.Status != 'kicked'
              AND i.Flow_Status != 'done'
            ORDER BY sa.EndDate DESC";
    return $this->runQuery($sql, [$client_id], true);
}



    public function getClientRentedUnitIds($client_id) {
        $sql = "SELECT cs.Space_ID
                FROM clientspace cs
                JOIN spaceavailability sa ON cs.Space_ID = sa.Space_ID
                WHERE cs.Client_ID = ? AND sa.Status='Occupied' AND sa.EndDate >= CURDATE()";
        $results = $this->runQuery($sql, [$client_id], true);
        return $results ? array_column($results, 'Space_ID') : [];
    }

    // --- AVAILABLE UNITS ---
    public function getAvailableUnitsForRental($id) {
        $sql = "SELECT s.Space_ID, s.Name, s.Price, st.SpaceTypeName
                FROM space s
                LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
                LEFT JOIN spaceavailability sa ON s.Space_ID = sa.Space_ID AND sa.Status = 'Occupied'
                WHERE sa.Status IS NULL AND s.Space_ID = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

  public function getHomepageAvailableUnits($limit = 6) {
    // Only show units that are available to rent (Flow_Status = 'new')
    $sql = "SELECT s.*, st.SpaceTypeName
            FROM space s
            LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
            LEFT JOIN (
                SELECT Space_ID FROM spaceavailability
                WHERE LOWER(Status) = 'occupied' AND EndDate >= CURDATE()
            ) sa ON s.Space_ID = sa.Space_ID
            WHERE sa.Space_ID IS NULL
              AND s.Flow_Status = 'new' -- Only available units
            ORDER BY s.Space_ID DESC
            LIMIT :limit";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}



public function getHomepageRentedUnits($limit = 12) {
    $sql = "SELECT 
            s.Space_ID, s.Name, s.Price, st.SpaceTypeName, s.Street, s.Brgy, s.City,
            sa.StartDate, sa.EndDate,
            c.Client_fn, c.Client_ln
        FROM clientspace cs
        JOIN space s ON cs.Space_ID = s.Space_ID
        LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
        LEFT JOIN spaceavailability sa ON sa.Space_ID = s.Space_ID
            AND sa.Status = 'Occupied'
            AND sa.EndDate = (
                SELECT MAX(sa2.EndDate) 
                FROM spaceavailability sa2
                WHERE sa2.Space_ID = s.Space_ID AND sa2.Status = 'Occupied'
            )
        JOIN (
            SELECT inv1.*
            FROM invoice inv1
            INNER JOIN (
                SELECT Client_ID, Space_ID, MAX(Created_At) AS max_created
                FROM invoice
                GROUP BY Client_ID, Space_ID
            ) inv2 ON inv1.Client_ID = inv2.Client_ID 
                 AND inv1.Space_ID = inv2.Space_ID 
                 AND inv1.Created_At = inv2.max_created
        ) i ON i.Client_ID = cs.Client_ID AND i.Space_ID = cs.Space_ID
        LEFT JOIN client c ON cs.Client_ID = c.Client_ID
        WHERE i.Status != 'kicked'
          AND i.Flow_Status != 'done'
        ORDER BY sa.EndDate DESC
        LIMIT :limit";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) { 
        return []; 
    }
}
public function updateJobTypeIcon($jobTypeId, $icon) {
    $sql = "UPDATE jobtype SET Icon = ? WHERE JobType_ID = ?";
    try {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$icon, $jobTypeId]);
    } catch (PDOException $e) {
        error_log("Error updating job type icon: " . $e->getMessage());
        return false;
    }
}



public function getJobTypeById($jobTypeId) {
    $sql = "SELECT * FROM jobtype WHERE JobType_ID = ?";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jobTypeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting job type by ID: " . $e->getMessage());
        return null;
    }
}


public function updateJobTypeWithImage($jobTypeId, $iconFile) {
    try {
        // Handle file upload - AUTO CREATE DIRECTORY
        $uploadDir = __DIR__ . "/../uploads/jobtype_icons/";
        
        // Automatically create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = 'jobtype_' . time() . '_' . uniqid() . '.' . pathinfo($iconFile['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($iconFile['tmp_name'], $uploadPath)) {
            $sql = "UPDATE jobtype SET Icon = ? WHERE JobType_ID = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$fileName, $jobTypeId]);
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error updating job type with image: " . $e->getMessage());
        return false;
    }
}


    // --- Feedback and Testimonials ---
    public function saveFeedback($invoice_id, $rating, $comments) {
    try {
        // The CS_ID column in clientfeedback should reference the clientspace CS_ID, not the invoice_id directly
        // But according to your constraint, it references invoice.Invoice_ID
        // So we need to use the invoice_id directly as CS_ID
        $sql = "INSERT INTO clientfeedback (CS_ID, Rating, Comments, Dates) VALUES (?, ?, ?, NOW())";
        $result = $this->executeStatement($sql, [$invoice_id, $rating, $comments]);
        if (!$result) {
            error_log("saveFeedback failed: " . print_r([$sql, $invoice_id, $rating, $comments], true));
        }
        return $result;
    } catch (PDOException $e) {
        error_log("saveFeedback PDOException: " . $e->getMessage());
        return false;
    }
}

    public function getFeedbackPrompts($client_id) {
        $sql = "SELECT i.Invoice_ID, i.InvoiceDate, s.Name AS SpaceName
                FROM invoice i
                JOIN space s ON i.Space_ID = s.Space_ID
                LEFT JOIN clientfeedback f ON f.CS_ID = i.Invoice_ID
                WHERE i.Client_ID = ? AND i.Status = 'kicked' AND f.Feedback_ID IS NULL";
        return $this->runQuery($sql, [$client_id], true);
    }


    
    public function getHomepageTestimonials($limit = 6) {
    $sql = "SELECT cf.*, c.Client_fn, c.Client_ln
        FROM clientfeedback cf
        JOIN invoice i ON cf.CS_ID = i.Invoice_ID
        JOIN client c ON i.Client_ID = c.Client_ID
        ORDER BY cf.Dates DESC
        LIMIT :limit";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
    }

    // --- Invoice and Payment ---
    public function getClientRentedUnitsList($client_id) {
        $sql = "SELECT DISTINCT s.Space_ID, s.Name AS SpaceName
                FROM clientspace cs
                JOIN space s ON cs.Space_ID = s.Space_ID
                WHERE cs.Client_ID = ?
                ORDER BY s.Name";
        return $this->runQuery($sql, [$client_id], true);
    }

    public function getClientInvoiceHistory($client_id) {
        $sql = "SELECT i.Invoice_ID, i.InvoiceDate, i.InvoiceTotal, i.Status, s.Name AS SpaceName, s.Space_ID
                FROM invoice i
                LEFT JOIN space s ON i.Space_ID = s.Space_ID
                WHERE i.Client_ID = ?
                ORDER BY i.InvoiceDate DESC";
        return $this->runQuery($sql, [$client_id], true);
    }

    public function getOverdueInvoices() {
        $sql = "SELECT i.Invoice_ID, i.Client_ID, i.Space_ID, s.Name AS SpaceName
                FROM invoice i
                JOIN space s ON i.Space_ID = s.Space_ID
                WHERE i.Status = 'unpaid' AND i.InvoiceDate < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        try { return $this->pdo->query($sql)->fetchAll(); } catch (PDOException $e) { return []; }
    }

    // --- Rental and Maintenance Requests ---
    public function createRentalRequest($client_id, $space_id, $start_date, $end_date) {
        $sql = "INSERT INTO rentalrequest (Client_ID, Space_ID, StartDate, EndDate, Status, Requested_At)
                VALUES (?, ?, ?, ?, 'Pending', NOW())";
        return $this->executeStatement($sql, [$client_id, $space_id, $start_date, $end_date]);
    }

public function getClientSpacesForMaintenance($client_id) {
    $sql = "SELECT s.Space_ID, s.Name
            FROM clientspace cs
            JOIN space s ON cs.Space_ID = s.Space_ID
            JOIN (
                SELECT inv1.*
                FROM invoice inv1
                INNER JOIN (
                    SELECT Client_ID, Space_ID, MAX(Created_At) AS max_created
                    FROM invoice
                    GROUP BY Client_ID, Space_ID
                ) inv2 ON inv1.Client_ID = inv2.Client_ID 
                       AND inv1.Space_ID = inv2.Space_ID 
                       AND inv1.Created_At = inv2.max_created
            ) i ON i.Client_ID = cs.Client_ID AND i.Space_ID = cs.Space_ID
            WHERE cs.Client_ID = ?
              AND i.Status != 'kicked'
              AND i.Flow_Status != 'done'
            ORDER BY s.Name";
    return $this->runQuery($sql, [$client_id], true);
}




    public function hasPendingMaintenanceRequest($client_id, $space_id) {
        $sql = "SELECT 1
                FROM maintenancerequest
                WHERE Client_ID = ? AND Space_ID = ? AND Status IN ('Submitted', 'In Progress')";
        return (bool)$this->runQuery($sql, [$client_id, $space_id]);
    }




public function createMaintenanceRequest($client_id, $space_id) {
    $this->pdo->beginTransaction();
    try {
        // Get current Philippine time from PHP
        $philippine_time = date('Y-m-d H:i:s');
        
        $sql1 = "INSERT INTO maintenancerequest (Client_ID, Space_ID, RequestDate, Status)
                 VALUES (?, ?, ?, 'Submitted')";
        $request_id = $this->insertAndGetId($sql1, [$client_id, $space_id, $philippine_time]);
        if (!$request_id) throw new Exception("Failed to create maintenance request.");
        
        $sql2 = "INSERT INTO maintenancerequeststatushistory (Request_ID, StatusChangeDate, NewStatus)
                 VALUES (?, ?, 'Submitted')";
        $this->executeStatement($sql2, [$request_id, $philippine_time]);
        
        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}






    public function sendInvoiceChatWithChatId($chat_id, $sender_type, $sender_id, $message, $image_path = null) {
    $sql = "INSERT INTO invoice_chat (Chat_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At)
            VALUES (?, ?, ?, ?, ?, NOW())";
    return $this->executeStatement($sql, [$chat_id, $sender_type, $sender_id, $message, $image_path]);
}

    
public function createNextRecurringInvoiceWithChat($invoice_id) {
    // Get the current invoice
    $invoice = $this->runQuery("SELECT * FROM invoice WHERE Invoice_ID = ?", [$invoice_id]);
    if (!$invoice) return false;

    // Get the latest invoice for this client/unit (use EndDate, NOT rentalrequest anymore!)
    $last_invoice = $this->runQuery(
        "SELECT * FROM invoice WHERE Client_ID = ? AND Space_ID = ? ORDER BY EndDate DESC LIMIT 1",
        [$invoice['Client_ID'], $invoice['Space_ID']]
    );
    if (!$last_invoice) return false;

    // Compute new period - CORRECTED based on your requirements
    $last_end = $last_invoice['EndDate'];
    
    // FIX: Start date (Issue Date) = Old Due Date
    $start_date = $last_end;
    
    // FIX: End date (Due Date) = Old Due Date + 1 month
    $end_date = date('Y-m-d', strtotime("$last_end +1 month"));

    $this->pdo->beginTransaction();
    try {
        // Insert new invoice and fetch new Invoice_ID (now with EndDate!)
        $this->executeStatement(
            "INSERT INTO invoice (Client_ID, Space_ID, InvoiceDate, EndDate, InvoiceTotal, Status, Flow_Status) VALUES (?, ?, ?, ?, ?, 'unpaid', 'new')",
            [$invoice['Client_ID'], $invoice['Space_ID'], $start_date, $end_date, $invoice['InvoiceTotal']]
        );
        $new_invoice_id = $this->pdo->lastInsertId();

        // Copy all messages from old invoice chat to new invoice chat
        $old_msgs = $this->runQueryAll(
            "SELECT * FROM invoice_chat WHERE Invoice_ID = ? ORDER BY Created_At ASC, Chat_ID ASC",
            [$invoice_id]
        );
        foreach ($old_msgs as $msg) {
            $this->executeStatement(
                "INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $new_invoice_id,
                    $msg['Sender_Type'],
                    $msg['Sender_ID'],
                    $msg['Message'],
                    $msg['Image_Path'],
                    $msg['Created_At']
                ]
            );
        }

        // Optionally add a system message
        $this->executeStatement(
            "INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At) VALUES (?, 'system', NULL, ?, NULL, NOW())",
            [$new_invoice_id, 'Conversation continued from previous invoice.']
        );

        $this->pdo->commit();
        return $new_invoice_id;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}

    public function getClientMaintenanceHistory($client_id) {
        $sql = "SELECT mr.Request_ID, mr.Space_ID, s.Name AS SpaceName, mr.RequestDate, mr.Status,
                       (SELECT MAX(StatusChangeDate) FROM maintenancerequeststatushistory WHERE Request_ID = mr.Request_ID) AS LastStatusDate,
                       h.Handyman_fn, h.Handyman_ln, mr.Handyman_ID
                FROM maintenancerequest mr
                JOIN space s ON mr.Space_ID = s.Space_ID
                LEFT JOIN handyman h ON mr.Handyman_ID = h.Handyman_ID
                WHERE mr.Client_ID = ?
                ORDER BY mr.RequestDate DESC";
        return $this->runQuery($sql, [$client_id], true);
    }

    public function getMaintenanceHistoryForUnits(array $unit_ids, $client_id) {
        if (empty($unit_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($unit_ids), '?'));
        $sql = "SELECT Space_ID, RequestDate, Status
                FROM maintenancerequest
                WHERE Client_ID = ? AND Space_ID IN ($placeholders)
                ORDER BY RequestDate DESC LIMIT 5";
        $params = array_merge([$client_id], $unit_ids);
        $history = $this->runQuery($sql, $params, true);
        $groupedHistory = [];
        if ($history) {
            foreach ($history as $item) {
                $groupedHistory[$item['Space_ID']][] = $item;
            }
        }
        return $groupedHistory;
    }

    // --- Handyman and Job Types ---
public function getAllHandymenWithJob() {
    $sql = "SELECT h.Handyman_ID, h.Handyman_fn, h.Handyman_ln, h.Phone, jt.JobType_Name, jt.Icon
            FROM handyman h
            LEFT JOIN handymanjob hj ON hj.Handyman_ID = h.Handyman_ID
            LEFT JOIN jobtype jt ON hj.JobType_ID = jt.JobType_ID
            ORDER BY h.Handyman_ln, h.Handyman_fn";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}





    public function getJobTypeNameById($jobtype_id) {
        $sql = "SELECT JobType_Name FROM jobtype WHERE JobType_ID = ?";
        return $this->runQuery($sql, [$jobtype_id]);
    }

    public function getHandymenByJobType($jobtype_id) {
        $sql = "SELECT h.Handyman_fn, h.Handyman_ln, h.Phone
                FROM handyman h
                INNER JOIN handymanjob hj ON hj.Handyman_ID = h.Handyman_ID
                WHERE hj.JobType_ID = ?
                ORDER BY h.Handyman_ln, h.Handyman_fn";
        return $this->runQuery($sql, [$jobtype_id], true) ?: [];
    }

  
    public function getHandymanById($handyman_id) {
    $sql = "SELECT h.Handyman_ID, h.Handyman_fn, h.Handyman_ln, h.Phone, hj.JobType_ID
            FROM handyman h
            LEFT JOIN handymanjob hj ON hj.Handyman_ID = h.Handyman_ID
            WHERE h.Handyman_ID = ? LIMIT 1";
    return $this->runQuery($sql, [$handyman_id]);
}




   public function addHandyman($fn, $ln, $phone, $jobtype_id) {
    $this->pdo->beginTransaction();
    try {
        $sql1 = "INSERT INTO handyman (Handyman_fn, Handyman_ln, Phone) VALUES (?,?,?)";
        $handyman_id = $this->insertAndGetId($sql1, [$fn, $ln, $phone]);
        if (!$handyman_id) throw new Exception("Failed to create handyman record.");

        $sql2 = "INSERT INTO handymanjob (Handyman_ID, JobType_ID) VALUES (?, ?)";
        $this->executeStatement($sql2, [$handyman_id, $jobtype_id]);

        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}
    

public function removeSpacePhoto($space_id, $photo_filename) {
    // Fetch current photo array
    $stmt = $this->pdo->prepare("SELECT Photo FROM space WHERE Space_ID = ?");
    $stmt->execute([$space_id]);
    $space = $stmt->fetch();
    $photos = [];
    if ($space && !empty($space['Photo'])) {
        $photos = json_decode($space['Photo'], true) ?: [];
    }

    // Remove the specified photo filename
    $photos = array_values(array_diff($photos, [$photo_filename]));

    // Update the Photo column with the new array
    $stmt = $this->pdo->prepare("UPDATE space SET Photo = ? WHERE Space_ID = ?");
    return $stmt->execute([json_encode($photos), $space_id]);
}



   public function updateHandyman($id, $fn, $ln, $phone, $jobtype_id) {
    $this->pdo->beginTransaction();
    try {
        $sql1 = "UPDATE handyman SET Handyman_fn=?, Handyman_ln=?, Phone=? WHERE Handyman_ID=?";
        $this->executeStatement($sql1, [$fn, $ln, $phone, $id]);

        $job_exists = $this->runQuery("SELECT 1 FROM handymanjob WHERE Handyman_ID = ?", [$id]);

        if ($job_exists) {
            $sql2 = "UPDATE handymanjob SET JobType_ID=? WHERE Handyman_ID=?";
            $this->executeStatement($sql2, [$jobtype_id, $id]);
        } else {
            $sql2 = "INSERT INTO handymanjob (Handyman_ID, JobType_ID) VALUES (?, ?)";
            $this->executeStatement($sql2, [$id, $jobtype_id]);
        }

        $this->pdo->commit();
        return true;
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        return false;
    }
}


 public function deleteHandyman($handyman_id) {
    $this->pdo->beginTransaction();
    try {
        $this->executeStatement("DELETE FROM handymanjob WHERE Handyman_ID = ?", [$handyman_id]);
        $this->executeStatement("DELETE FROM handyman WHERE Handyman_ID = ?", [$handyman_id]);
        $this->pdo->commit();
        return true;
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        return false;
    }
}



      public function getAllJobTypes() {
    $sql = "SELECT JobType_ID, JobType_Name, Icon FROM jobtype ORDER BY JobType_Name";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}



    public function addJobType($jobTypeName) {
    $sql = "INSERT INTO jobtype (JobType_Name) VALUES (:name)";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':name', $jobTypeName, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error adding job type: " . $e->getMessage());
        return false;
    }
}







    // --- Admin Actions ---
    public function kickClientFromUnit($invoice_id, $client_id, $space_id) {
        $this->pdo->beginTransaction();
        try {
            $this->executeStatement("DELETE FROM clientspace WHERE Client_ID = ? AND Space_ID = ?", [$client_id, $space_id]);
            $this->executeStatement("UPDATE spaceavailability SET Status = 'Available', EndDate = CURDATE() WHERE Space_ID = ? AND Status = 'Occupied'", [$space_id]);
            $this->executeStatement("UPDATE invoice SET Status = 'kicked' WHERE Invoice_ID = ?", [$invoice_id]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to kick client $client_id for invoice $invoice_id: " . $e->getMessage());
            return false;
        }
    }

     public function addSpaceType($spaceTypeName) {
        $sql = "INSERT INTO spacetype (SpaceTypeName) VALUES (?)";
        return $this->executeStatement($sql, [$spaceTypeName]);
    }
    
    public function getAllSpaceTypes() {
        $sql = "SELECT SpaceType_ID, SpaceTypeName FROM spacetype ORDER BY SpaceTypeName ASC";
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function isSpaceNameExists($name) {
        $sql = "SELECT 1 FROM space WHERE LOWER(TRIM(Name)) = LOWER(TRIM(?))";
        return (bool)$this->runQuery($sql, [$name]);
    }

    
public function addNewSpace($name, $spacetype_id, $ua_id, $price) {
    $street = 'General Luna Strt';
    $brgy = '10';
    $city = 'Lipa City';
    $avail_status = 'Available';

    $this->pdo->beginTransaction();
    try {
        // REMOVED: Photo column from the INSERT statement
        $sql1 = "INSERT INTO space (
                    Name, SpaceType_ID, UA_ID, Street, Brgy, City, Price, Flow_Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')";
        
        // Use your existing insertAndGetId method
        $space_id = $this->insertAndGetId($sql1, [
            $name,                // Name
            $spacetype_id,        // SpaceType_ID
            $ua_id,               // UA_ID
            $street,              // Street
            $brgy,                // Brgy
            $city,                // City
            $price                // Price
        ]);

        if (!$space_id) {
            throw new Exception("Failed to create space record.");
        }

        $sql2 = "INSERT INTO spaceavailability (Space_ID, Status) VALUES (?, ?)";
        $this->executeStatement($sql2, [$space_id, $avail_status]);

        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        error_log("addNewSpace Error: " . $e->getMessage());
        return false;
    }
}



public function addFirstPhotoToSpace($space_id, $filename, $action_by = null) {
    try {
        // Create new photos array with the first photo
        $photos = [$filename];
        $photo_json = json_encode($photos);
        
        // Update the space with the first photo
        $sql = "UPDATE space SET Photo = ? WHERE Space_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$photo_json, $space_id]);
        
        if ($result) {
            // Log the first photo upload in history
            $this->logPhotoAction($space_id, $filename, 'uploaded', null, $action_by);
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Add first photo to space error: " . $e->getMessage());
        return false;
    }
}


public function getAllSpacesWithDetails() {
    $sql = "SELECT s.*, t.SpaceTypeName 
            FROM space s
            LEFT JOIN spacetype t ON s.SpaceType_ID = t.SpaceType_ID
            ORDER BY s.Space_ID DESC";
    try {
        $spaces = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $spaces = array_filter($spaces, function($s) {
            return !empty($s['Space_ID']);
        });
        return $spaces;
    } catch (PDOException $e) {
        error_log("getAllSpacesWithDetails Error: " . $e->getMessage());
        return [];
    }
}
    
    
    
    // --- Overdue Rentals For Kicking ---



  public function getAllActiveRenters() {
        $sql = "SELECT DISTINCT 
                    i.Invoice_ID,
                    i.Client_ID,
                    i.Space_ID,
                    i.InvoiceDate,
                    i.EndDate,
                    i.InvoiceTotal,
                    i.Status,
                    i.Flow_Status,
                    c.Client_fn,
                    c.Client_ln,
                    c.Client_Email,
                    c.Client_Phone,
                    s.Name as SpaceName,
                    s.Street,
                    s.Brgy,
                    s.City,
                    r.Request_ID,
                    cs.CS_ID,
                    DATEDIFF(CURDATE(), i.EndDate) as DaysOverdue,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), i.EndDate) > 0 THEN 'overdue'
                        WHEN DATEDIFF(CURDATE(), i.EndDate) = 0 THEN 'due_today' 
                        ELSE 'current'
                    END as RentalStatus
                FROM invoice i
                JOIN client c ON i.Client_ID = c.Client_ID
                JOIN space s ON i.Space_ID = s.Space_ID
                JOIN clientspace cs ON i.Client_ID = cs.Client_ID 
                    AND i.Space_ID = cs.Space_ID 
                LEFT JOIN rentalrequest r ON i.Client_ID = r.Client_ID 
                    AND i.Space_ID = r.Space_ID 
                    AND r.Status = 'Accepted'
                WHERE i.Flow_Status = 'new'
                ORDER BY 
                    CASE 
                        WHEN i.Status = 'unpaid' AND DATEDIFF(CURDATE(), i.EndDate) > 0 THEN 1
                        WHEN i.Status = 'unpaid' AND DATEDIFF(CURDATE(), i.EndDate) = 0 THEN 2
                        WHEN i.Status = 'unpaid' THEN 3
                        ELSE 4
                    END,
                    DATEDIFF(CURDATE(), i.EndDate) DESC,
                    i.EndDate ASC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all active renters: " . $e->getMessage());
            return [];
        }
    }
    
    // Updated kick overdue client method with full admin control
    public function kickOverdueClient($invoice_id, $client_id, $space_id, $request_id) {
        // Validate input parameters
        if (!is_numeric($invoice_id) || !is_numeric($client_id) || !is_numeric($space_id)) {
            error_log("Invalid parameters for kickOverdueClient");
            return false;
        }
        
        $this->pdo->beginTransaction();
        try {
            // 1. Deactivate client-space relationship (soft delete instead of hard delete)
            $stmt1 = $this->pdo->prepare("UPDATE clientspace SET active = 0 WHERE Client_ID = ? AND Space_ID = ? AND active = 1");
            $stmt1->execute([$client_id, $space_id]);

            // 2. Set spaceavailability to 'Available' and set EndDate to today
            $stmt2 = $this->pdo->prepare("UPDATE spaceavailability 
                         SET Status = 'Available', EndDate = CURDATE() 
                         WHERE Space_ID = ? AND Status = 'Occupied'");
            $stmt2->execute([$space_id]);

            // 3. Mark the invoice as 'kicked' and set Flow_Status to 'done'
            $stmt3 = $this->pdo->prepare("UPDATE invoice 
                         SET Status = 'kicked', Flow_Status = 'done' 
                         WHERE Invoice_ID = ? AND Client_ID = ?");
            $stmt3->execute([$invoice_id, $client_id]);

            // 4. Mark the rental request as 'Rejected' (with null check)
            if ($request_id && is_numeric($request_id)) {
                $stmt4 = $this->pdo->prepare("UPDATE rentalrequest 
                             SET Status = 'Rejected' 
                             WHERE Request_ID = ? AND Client_ID = ? AND Space_ID = ?");
                $stmt4->execute([$request_id, $client_id, $space_id]);
            }

            // 5. Set the space as available in the flow (Flow_Status: 'new')
            $stmt5 = $this->pdo->prepare("UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?");
            $stmt5->execute([$space_id]);

            // 6. Ensure there is an 'Available' record in spaceavailability for this space (avoid duplicates)
            $existsStmt = $this->pdo->prepare("SELECT COUNT(*) FROM spaceavailability WHERE Space_ID = ? AND Status = 'Available'");
            $existsStmt->execute([$space_id]);
            
            if ($existsStmt->fetchColumn() == 0) {
                $insertStmt = $this->pdo->prepare("INSERT INTO spaceavailability (Space_ID, Status) VALUES (?, 'Available')");
                $insertStmt->execute([$space_id]);
            }

            // 7. Log the eviction for audit trail with admin context
            try {
                $message = "Client evicted by admin. Invoice: #{$invoice_id}, Admin User: " . ($_SESSION['username'] ?? 'Unknown');
                $logStmt = $this->pdo->prepare("INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Message, Created_At) 
                             VALUES (?, 'system', ?, NOW())");
                $logStmt->execute([$invoice_id, $message]);
            } catch (PDOException $e) {
                // Don't fail transaction for logging issues
                error_log("Failed to log eviction: " . $e->getMessage());
            }

            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to kick client (Invoice #{$invoice_id}): " . $e->getMessage());
            return false;
        }
    }
    
    // Get overdue rentals for kicking (original method - kept for compatibility)
    public function getOverdueRentalsForKicking() {
        $sql = "SELECT DISTINCT 
                    i.Invoice_ID, 
                    i.Client_ID, 
                    i.Space_ID, 
                    i.InvoiceDate, 
                    i.EndDate,
                    i.Status, 
                    c.Client_fn, 
                    c.Client_ln, 
                    s.Name as SpaceName, 
                    r.Request_ID,
                    DATEDIFF(CURDATE(), i.EndDate) as DaysOverdue
                FROM invoice i
                JOIN client c ON i.Client_ID = c.Client_ID
                JOIN space s ON i.Space_ID = s.Space_ID
                LEFT JOIN rentalrequest r ON i.Client_ID = r.Client_ID 
                    AND i.Space_ID = r.Space_ID 
                    AND r.Status = 'Accepted'
                WHERE i.Status = 'unpaid'
                    AND i.Flow_Status = 'new'
                    AND i.EndDate <= CURDATE()
                    AND c.Status = 'Active'
                ORDER BY i.EndDate ASC";
        
        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching overdue rentals: " . $e->getMessage());
            return [];
        }
    }

























    public function getMaintenanceRequestStatus($request_id) {
        $sql = "SELECT Status FROM maintenancerequest WHERE Request_ID = ?";
        $result = $this->runQuery($sql, [$request_id]);
        return $result ? $result['Status'] : false;
    }

    public function updateMaintenanceStatus($request_id, $new_status) {
        $this->pdo->beginTransaction();
        try {
            $sql1 = "UPDATE maintenancerequest SET Status = ? WHERE Request_ID = ?";
            $this->executeStatement($sql1, [$new_status, $request_id]);
            $sql2 = "INSERT INTO maintenancerequeststatushistory (Request_ID, StatusChangeDate, NewStatus) VALUES (?, NOW(), ?)";
            $this->executeStatement($sql2, [$request_id, $new_status]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getClientInfo($client_id) {
    $sql = "SELECT Client_fn, Client_ln, Client_Email, Client_Phone 
            FROM client 
            WHERE Client_ID = ?";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$client_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}


public function getPendingRequestsByClient($client_id) {
    $sql = "SELECT rr.*, rr.Requested_At AS RequestDate, s.Name 
            FROM rentalrequest rr 
            JOIN space s ON rr.Space_ID = s.Space_ID 
            WHERE rr.Client_ID = ? AND rr.Status = 'Pending'";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$client_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

public function getPendingRentalRequests() {
    $sql = "SELECT r.Request_ID, c.Client_fn, c.Client_ln, c.Client_Email, c.Client_Phone, 
                   s.Name, r.StartDate, r.EndDate, r.Status, r.Requested_At, r.admin_seen
            FROM rentalrequest r
            JOIN client c ON r.Client_ID = c.Client_ID
            JOIN space s ON r.Space_ID = s.Space_ID
            WHERE r.Status = 'Pending'
            ORDER BY r.Requested_At ASC";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

    public function getAdminByUsername($username) {
        $sql = "SELECT UA_ID, username, password FROM useraccounts WHERE username = ? AND Type = 'Admin'";
        return $this->runQuery($sql, [$username]);
    }



    

public function getMonthlyEarningsStats($startDate, $endDate) {
    // Include the time component to cover the entire end date
    $endDateWithTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
        COALESCE(SUM(InvoiceTotal), 0) as total_earnings,
        COUNT(CASE WHEN Status = 'paid' THEN 1 END) as paid_invoices_count,
        (SELECT COUNT(*) FROM free_message WHERE is_deleted = 0 AND Sent_At BETWEEN ? AND ?) as new_messages_count
        FROM invoice 
        WHERE Status = 'paid' 
        AND InvoiceDate BETWEEN ? AND ?"; // Use InvoiceDate instead of Created_At
    
    return $this->getRow($sql, [
        $startDate, $endDateWithTime, 
        $startDate, $endDate
    ]);
}




public function getAdminDashboardCounts($startDate = null, $endDate = null) {
    // If no dates provided, use current month
    if (!$startDate || !$endDate) {
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
    }
    
    // Add time component to include the entire end date
    $endDateWithTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
        (SELECT COUNT(*) FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new') as pending_rentals,
        (SELECT COUNT(*) FROM maintenancerequest WHERE Status IN ('Submitted', 'In Progress')) as pending_maintenance,
        (SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid') as unpaid_invoices,
        (SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND EndDate < CURDATE()) as overdue_invoices,
        (SELECT COUNT(*) FROM maintenancerequest WHERE Status = 'Submitted' AND admin_seen = 0) as new_maintenance_requests";
    
    return $this->getRow($sql); // Remove date parameters for real-time counts
}


// New function specifically for maintenance statistics
public function getMaintenanceStats($startDate, $endDate) {
    $sql = "SELECT 
        COUNT(*) as total_maintenance,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) as completed_maintenance,
        COUNT(CASE WHEN Status = 'In Progress' THEN 1 END) as in_progress_maintenance,
        COUNT(CASE WHEN Status IN ('Submitted', 'In Progress') THEN 1 END) as pending_maintenance
        FROM maintenancerequest 
        WHERE RequestDate BETWEEN ? AND ?";
    
    return $this->getRow($sql, [$startDate, $endDate]);
}

public function getTotalRentalRequests($startDate, $endDate) {
    // Include the time component to cover the entire end date
    $endDateWithTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
        COUNT(*) as total_rental_requests,
        COUNT(CASE WHEN Status = 'Pending' THEN 1 END) as pending_rentals,
        COUNT(CASE WHEN Status = 'Accepted' THEN 1 END) as accepted_rentals,
        COUNT(CASE WHEN Status = 'Rejected' THEN 1 END) as rejected_rentals
        FROM rentalrequest 
        WHERE Requested_At BETWEEN ? AND ?";
    
    $result = $this->getRow($sql, [$startDate, $endDateWithTime]);
    return [
        'total' => $result['total_rental_requests'] ?? 0,
        'pending' => $result['pending_rentals'] ?? 0,
        'accepted' => $result['accepted_rentals'] ?? 0,
        'rejected' => $result['rejected_rentals'] ?? 0
    ];
}


public function getTotalMaintenanceRequests($startDate, $endDate) {
    // Include the time component to cover the entire end date
    $endDateWithTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
        COUNT(*) as total_maintenance,
        COUNT(CASE WHEN Status = 'Submitted' THEN 1 END) as submitted_requests,
        COUNT(CASE WHEN Status = 'In Progress' THEN 1 END) as in_progress_requests,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) as completed_requests
        FROM maintenancerequest 
        WHERE RequestDate BETWEEN ? AND ?";
    
    $result = $this->getRow($sql, [$startDate, $endDateWithTime]);
    return [
        'total' => $result['total_maintenance'] ?? 0,
        'submitted' => $result['submitted_requests'] ?? 0,
        'in_progress' => $result['in_progress_requests'] ?? 0,
        'completed' => $result['completed_requests'] ?? 0
    ];
}


public function getDetailedRentalData($startDate, $endDate) {
    try {
        $sql = "SELECT rr.*, c.Client_fn, c.Client_ln, c.Client_Email, s.Name as UnitName, s.Price
                FROM rentalrequest rr
                JOIN client c ON rr.Client_ID = c.Client_ID
                LEFT JOIN space s ON rr.Space_ID = s.Space_ID
                WHERE rr.Requested_At BETWEEN ? AND ?
                ORDER BY rr.Requested_At DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting detailed rental data: " . $e->getMessage());
        return [];
    }
}

public function getDetailedMaintenanceData($startDate, $endDate) {
    try {
        $sql = "SELECT mr.*, c.Client_fn, c.Client_ln, s.Name as UnitName, 
                       h.Handyman_fn, h.Handyman_ln,
                       (SELECT MAX(StatusChangeDate) FROM maintenancerequeststatushistory 
                        WHERE Request_ID = mr.Request_ID AND NewStatus = 'Completed') as CompletionDate
                FROM maintenancerequest mr
                JOIN client c ON mr.Client_ID = c.Client_ID
                LEFT JOIN space s ON mr.Space_ID = s.Space_ID
                LEFT JOIN handyman h ON mr.Handyman_ID = h.Handyman_ID
                WHERE mr.RequestDate BETWEEN ? AND ?
                ORDER BY mr.RequestDate DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting detailed maintenance data: " . $e->getMessage());
        return [];
    }
}

public function getDetailedInvoiceData($startDate, $endDate) {
    try {
        $sql = "SELECT i.*, c.Client_fn, c.Client_ln, s.Name as UnitName
                FROM invoice i
                JOIN client c ON i.Client_ID = c.Client_ID
                LEFT JOIN space s ON i.Space_ID = s.Space_ID
                WHERE i.InvoiceDate BETWEEN ? AND ?
                ORDER BY i.InvoiceDate DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting detailed invoice data: " . $e->getMessage());
        return [];
    }
}
    
public function getLatestPendingRequests($limit = 5) {
    $sql = "SELECT rr.Request_ID, rr.Requested_At, rr.Status, rr.admin_seen, rr.Flow_Status,
                   c.Client_ID, c.Client_fn, c.Client_ln, c.Client_Email, c.Client_Phone,
                   s.Space_ID, s.Name AS UnitName, s.Price,
                   rr.StartDate, rr.EndDate,
                   CASE 
                       WHEN rr.admin_seen = 0 THEN 'New'
                       ELSE 'Seen' 
                   END as request_status
            FROM rentalrequest rr
            LEFT JOIN client c ON rr.Client_ID = c.Client_ID
            LEFT JOIN space s ON rr.Space_ID = s.Space_ID
            WHERE rr.Status = 'Pending' 
            AND rr.Flow_Status = 'new'
            ORDER BY 
                rr.admin_seen ASC,  -- Show unseen requests first
                rr.Requested_At DESC
            LIMIT ?";

    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting latest pending requests: " . $e->getMessage());
        return [];
    }
}

// Add these methods to your Database class
public function markRentalRequestsAsSeen() {
    try {
        $sql = "UPDATE rentalrequest SET admin_seen = 1 WHERE admin_seen = 0 AND Status = 'Pending'";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error marking rental requests as seen: " . $e->getMessage());
        return false;
    }
}

public function markMaintenanceRequestsAsSeen() {
    try {
        $sql = "UPDATE maintenancerequest SET admin_seen = 1 WHERE admin_seen = 0 AND Status = 'Submitted'";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error marking maintenance requests as seen: " . $e->getMessage());
        return false;
    }
}
  

public function markInvoiceAsPaid($invoice_id) {
    $invoice_details = $this->runQuery("SELECT Space_ID, InvoiceTotal FROM invoice WHERE Invoice_ID = ?", [$invoice_id]);
    if (!$invoice_details) {
        return false;
    }

    $this->pdo->beginTransaction();
    try {
        // Update both Status and Flow_Status
        $this->executeStatement(
            "UPDATE invoice SET Status = 'paid', Flow_Status = 'done' WHERE Invoice_ID = ?",
            [$invoice_id]
        );

        $this->executeStatement(
            "INSERT INTO transaction (Space_ID, Invoice_ID, TransactionDate, Total_Amount) VALUES (?, ?, CURDATE(), ?)",
            [$invoice_details['Space_ID'], $invoice_id, $invoice_details['InvoiceTotal']]
        );

        $this->pdo->commit();
        return true;

    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}

public function getRecentFreeMessages($limit = 5) {
    $sql = "SELECT * FROM free_message WHERE is_deleted = 0 ORDER BY Sent_At DESC LIMIT ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

public function getAllFreeMessages() {
    $sql = "SELECT * FROM free_message ORDER BY Sent_At DESC";
    return $this->runQueryAll($sql);
}

public function insertFreeMessage($name, $email, $phone, $message) {
    $sql = "INSERT INTO free_message (Client_Name, Client_Email, Client_Phone, Message_Text)
            VALUES (?, ?, ?, ?)";
    return $this->executeStatement($sql, [$name, $email, $phone, $message]);
}

public function getSingleInvoiceForDisplay($invoice_id) {
    $sql = "SELECT i.*, c.Client_fn, c.Client_ln, c.Client_Email, s.Name AS UnitName
            FROM invoice i
            LEFT JOIN client c ON i.Client_ID = c.Client_ID
            LEFT JOIN space s ON i.Space_ID = s.Space_ID
            WHERE i.Invoice_ID = ?
            LIMIT 1";
    $result = $this->runQuery($sql, [$invoice_id]);
    return $result;
}


    public function getAllUnpaidInvoices() {
        $sql = "SELECT i.Invoice_ID, c.Client_fn, c.Client_ln, s.Name AS UnitName,
                       i.InvoiceDate, i.Status, r.EndDate
                FROM invoice i
                INNER JOIN client c ON i.Client_ID = c.Client_ID
                INNER JOIN space s ON i.Space_ID = s.Space_ID
                LEFT JOIN rentalrequest r ON r.Client_ID = i.Client_ID AND r.Space_ID = i.Space_ID AND r.Status = 'Accepted'
                WHERE i.Status = 'unpaid'
                ORDER BY r.EndDate DESC, i.InvoiceDate ASC";
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // --- Rental Request ---
public function acceptRentalRequest($request_id) {
    $request_details = $this->runQuery(
        "SELECT r.Client_ID, r.Space_ID, r.StartDate, r.EndDate, s.Price 
         FROM rentalrequest r 
         JOIN space s ON r.Space_ID = s.Space_ID
         WHERE r.Request_ID = ? AND r.Status = 'Pending'",
        [$request_id]
    );

    if (!$request_details) {
        return false;
    }

    $this->pdo->beginTransaction();
    try {
        // 1. Mark request as accepted AND seen
        $this->executeStatement(
            "UPDATE rentalrequest SET Status = 'Accepted', admin_seen = 1 WHERE Request_ID = ?",
            [$request_id]
        );
        // 2. Mark space as occupied in availability
        $this->executeStatement(
            "INSERT INTO spaceavailability (Space_ID, StartDate, EndDate, Status) VALUES (?, ?, ?, 'Occupied')",
            [$request_details['Space_ID'], $request_details['StartDate'], $request_details['EndDate']]
        );
        // 3. Ensure clientspace link exists
        $is_linked = $this->runQuery("SELECT 1 FROM clientspace WHERE Space_ID = ? AND Client_ID = ?", [$request_details['Space_ID'], $request_details['Client_ID']]);
        if (!$is_linked) {
            $this->executeStatement(
                "INSERT INTO clientspace (Space_ID, Client_ID) VALUES (?, ?)",
                [$request_details['Space_ID'], $request_details['Client_ID']]
            );
        }
        // 4. Create the invoice (with EndDate and Flow_Status)
        $this->executeStatement(
            "INSERT INTO invoice (Client_ID, Space_ID, InvoiceDate, EndDate, InvoiceTotal, Status, Flow_Status) VALUES (?, ?, ?, ?, ?, 'unpaid', 'new')",
            [
                $request_details['Client_ID'],
                $request_details['Space_ID'],
                $request_details['StartDate'],
                $request_details['EndDate'],
                $request_details['Price']
            ]
        );
        // 5. Update space flow status to 'old' (not available anymore)
        $this->executeStatement(
            "UPDATE space SET Flow_Status = 'old' WHERE Space_ID = ?",
            [$request_details['Space_ID']]
        );
        $this->pdo->commit();
        return true;

    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}

    public function getActiveMaintenanceRequests() {
        $sql = "SELECT mr.Request_ID, c.Client_fn, c.Client_ln, s.Name AS SpaceName, 
                       mr.RequestDate, mr.Status, mr.Handyman_ID,
                       h.Handyman_fn, h.Handyman_ln,
                       (SELECT GROUP_CONCAT(jt.JobType_Name SEPARATOR ', ')
                        FROM handymanjob hj
                        JOIN jobtype jt ON hj.JobType_ID = jt.JobType_ID
                        WHERE hj.Handyman_ID = h.Handyman_ID) AS JobTypes
                FROM maintenancerequest mr
                JOIN client c ON mr.Client_ID = c.Client_ID
                JOIN space s ON mr.Space_ID = s.Space_ID
                LEFT JOIN handyman h ON mr.Handyman_ID = h.Handyman_ID
                WHERE mr.Status IN ('Submitted', 'In Progress')
                ORDER BY mr.RequestDate DESC";
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getInProgressMaintenanceRequests() {
    $sql = "SELECT mr.Request_ID, c.Client_fn, c.Client_ln, s.Name AS SpaceName, 
                   mr.RequestDate, mr.Status, mr.Handyman_ID,
                   h.Handyman_fn, h.Handyman_ln,
                   (SELECT GROUP_CONCAT(jt.JobType_Name SEPARATOR ', ')
                    FROM handymanjob hj
                    JOIN jobtype jt ON hj.JobType_ID = jt.JobType_ID
                    WHERE hj.Handyman_ID = h.Handyman_ID) AS JobTypes
            FROM maintenancerequest mr
            JOIN client c ON mr.Client_ID = c.Client_ID
            JOIN space s ON mr.Space_ID = s.Space_ID
            LEFT JOIN handyman h ON mr.Handyman_ID = h.Handyman_ID
            WHERE mr.Status = 'In Progress'
            ORDER BY mr.RequestDate DESC";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

public function getCompletedMaintenanceRequests() {
    $sql = "SELECT mr.Request_ID, c.Client_fn, c.Client_ln, s.Name AS SpaceName, 
                   mr.RequestDate, mr.Status, mr.Handyman_ID,
                   h.Handyman_fn, h.Handyman_ln,
                   (SELECT GROUP_CONCAT(jt.JobType_Name SEPARATOR ', ')
                    FROM handymanjob hj
                    JOIN jobtype jt ON hj.JobType_ID = jt.JobType_ID
                    WHERE hj.Handyman_ID = h.Handyman_ID) AS JobTypes
            FROM maintenancerequest mr
            JOIN client c ON mr.Client_ID = c.Client_ID
            JOIN space s ON mr.Space_ID = s.Space_ID
            LEFT JOIN handyman h ON mr.Handyman_ID = h.Handyman_ID
            WHERE mr.Status = 'Completed'
            ORDER BY mr.RequestDate DESC";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}



public function getRequestCompletionDate($requestId) {
    try {
        $sql = "SELECT StatusChangeDate FROM maintenancerequeststatushistory 
                WHERE Request_ID = ? AND NewStatus = 'Completed' 
                ORDER BY StatusChangeDate DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$requestId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? date('M j, Y g:i A', strtotime($result['StatusChangeDate'])) : null;
    } catch (Exception $e) {
        error_log("Error getting completion date: " . $e->getMessage());
        return null;
    }
}



    public function getAllHandymenWithJobTypes() {
        $sql = "SELECT h.Handyman_ID, h.Handyman_fn, h.Handyman_ln, 
                       GROUP_CONCAT(jt.JobType_Name SEPARATOR ', ') AS JobTypes
                FROM handyman h
                LEFT JOIN handymanjob hj ON h.Handyman_ID = hj.Handyman_ID
                LEFT JOIN jobtype jt ON hj.JobType_ID = jt.JobType_ID
                GROUP BY h.Handyman_ID
                ORDER BY h.Handyman_fn";
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }


    public function updateMaintenanceRequest($request_id, $new_status, $handyman_id) {
        $current = $this->runQuery("SELECT Status FROM maintenancerequest WHERE Request_ID = ?", [$request_id]);
        $status_changed = ($current && $current['Status'] !== $new_status);

        $this->pdo->beginTransaction();
        try {
            $sql1 = "UPDATE maintenancerequest SET Status = ?, Handyman_ID = ? WHERE Request_ID = ?";
            $this->executeStatement($sql1, [$new_status, $handyman_id, $request_id]);
            if ($status_changed) {
                $sql2 = "INSERT INTO maintenancerequeststatushistory (Request_ID, StatusChangeDate, NewStatus) VALUES (?, NOW(), ?)";
                $this->executeStatement($sql2, [$request_id, $new_status]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Failed to update maintenance request #{$request_id}: " . $e->getMessage());
            return false;
        }
    }

    public function markMaintenanceRequestAsSeen($requestId) {
    try {
        $sql = "UPDATE maintenancerequest SET admin_seen = 1 WHERE Request_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$requestId]);
        return true;
    } catch (Exception $e) {
        error_log("Error marking maintenance request as seen: " . $e->getMessage());
        return false;
    }
}

    

    public function updateClientStatus($client_id, $status) {
        $sql = "UPDATE client SET Status = ? WHERE Client_ID = ?";
        return $this->executeStatement($sql, [$status, $client_id]);
    }

    public function hardDeleteClient($client_id) {
        $this->pdo->beginTransaction();
        try {
            $rented_spaces = $this->runQuery("SELECT Space_ID FROM clientspace WHERE Client_ID = ?", [$client_id], true);
            if ($rented_spaces) {
                foreach ($rented_spaces as $space) {
                    $this->executeStatement(
                        "UPDATE spaceavailability SET Status = 'available' WHERE Space_ID = ? AND Status = 'Occupied'",
                        [$space['Space_ID']]
                    );
                    // Also set Flow_Status to 'new' in the space table
                    $this->executeStatement(
                        "UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?",
                        [$space['Space_ID']]
                    );
                }
            }
            $invoices = $this->runQuery("SELECT Invoice_ID FROM invoice WHERE Client_ID = ?", [$client_id], true);
            $invoice_ids = $invoices ? array_column($invoices, 'Invoice_ID') : [];

            if (!empty($invoice_ids)) {
                $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
                $this->executeStatement("DELETE FROM transaction WHERE Invoice_ID IN ($placeholders)", $invoice_ids);
                $this->executeStatement("DELETE FROM clientfeedback WHERE CS_ID IN ($placeholders)", $invoice_ids);
            }

            $this->executeStatement("DELETE FROM clientspace WHERE Client_ID = ?", [$client_id]);
            $this->executeStatement("DELETE FROM maintenancerequest WHERE Client_ID = ?", [$client_id]);
            $this->executeStatement("DELETE FROM invoice WHERE Client_ID = ?", [$client_id]);
            $this->executeStatement("DELETE FROM rentalrequest WHERE Client_ID = ?", [$client_id]);
            $this->executeStatement("DELETE FROM client WHERE Client_ID = ?", [$client_id]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // --- Methods for a single Unit/Space ---
    public function isUnitRented($space_id) {
        $sql = "SELECT 1 FROM clientspace WHERE Space_ID = ? LIMIT 1";
        return (bool)$this->runQuery($sql, [$space_id]);
    }

    public function updateUnit_price($space_id, $price) {
        $sql = "UPDATE space SET Price = ? WHERE Space_ID = ?";
        return $this->executeStatement($sql, [$price, $space_id]);
    }




public function hardDeleteUnit($space_id) {
    $this->pdo->beginTransaction();
    try {
        // 1. Find all active renters for this unit
        $activeRenters = $this->runQuery(
            "SELECT Client_ID FROM clientspace WHERE Space_ID = ? AND active = 1",
            [$space_id],
            true
        );

        if ($activeRenters) {
            foreach ($activeRenters as $renter) {
                $client_id = $renter['Client_ID'];

                // Deactivate clientspace
                $this->executeStatement(
                    "UPDATE clientspace SET active = 0 WHERE Client_ID = ? AND Space_ID = ?",
                    [$client_id, $space_id]
                );

                // Mark invoices as kicked
                $this->executeStatement(
                    "UPDATE invoice 
                     SET Status = 'kicked', Flow_Status = 'done' 
                     WHERE Client_ID = ? AND Space_ID = ? AND Status != 'kicked'",
                    [$client_id, $space_id]
                );

                // Reject rental requests
                $this->executeStatement(
                    "UPDATE rentalrequest 
                     SET Status = 'Rejected' 
                     WHERE Client_ID = ? AND Space_ID = ? AND Status != 'Rejected'",
                    [$client_id, $space_id]
                );
            }
        }

        // 2. Update spaceavailability to mark the space as ended
        $this->executeStatement(
            "UPDATE spaceavailability 
             SET Status = 'Available', EndDate = CURDATE() 
             WHERE Space_ID = ? AND Status = 'Occupied'",
            [$space_id]
        );

        // 3. Reset the flow status of the space
        $this->executeStatement("UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?", [$space_id]);

        // 4. Delete all related records and the unit itself
        $this->executeStatement("DELETE FROM spaceavailability WHERE Space_ID = ?", [$space_id]);
        $this->executeStatement("DELETE FROM clientspace WHERE Space_ID = ?", [$space_id]);
        $this->executeStatement("DELETE FROM rentalrequest WHERE Space_ID = ?", [$space_id]);
        $this->executeStatement("DELETE FROM maintenancerequest WHERE Space_ID = ?", [$space_id]);
        $this->executeStatement("DELETE FROM invoice WHERE Space_ID = ?", [$space_id]);
        $this->executeStatement("DELETE FROM space WHERE Space_ID = ?", [$space_id]);

        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}



public function getAllClientsWithOrWithoutUnit() {
    $sql = "SELECT 
                c.Client_ID,
                c.Client_fn,
                c.Client_ln,
                c.Client_Email,
                c.C_username,
                c.Status,
                CASE 
                    WHEN i.Status = 'kicked' OR i.Flow_Status = 'done' THEN NULL
                    ELSE s.Name
                END AS SpaceName,
                CASE 
                    WHEN i.Status = 'kicked' OR i.Flow_Status = 'done' THEN NULL
                    ELSE st.SpaceTypeName
                END AS SpaceTypeName
            FROM client c
            LEFT JOIN clientspace cs ON c.Client_ID = cs.Client_ID
            LEFT JOIN space s ON cs.Space_ID = s.Space_ID
            LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
            LEFT JOIN (
                SELECT inv1.*
                FROM invoice inv1
                INNER JOIN (
                    SELECT Client_ID, Space_ID, MAX(Created_At) AS max_created
                    FROM invoice
                    GROUP BY Client_ID, Space_ID
                ) inv2
                ON inv1.Client_ID = inv2.Client_ID AND inv1.Space_ID = inv2.Space_ID AND inv1.Created_At = inv2.max_created
            ) i ON i.Client_ID = c.Client_ID AND i.Space_ID = s.Space_ID
            ORDER BY c.Client_ID DESC";
    return $this->runQuery($sql, [], true);
}




public function getAllUnitsWithRenterInfo() {
    $sql = "SELECT 
                s.Space_ID, 
                s.Name, 
                s.SpaceType_ID, 
                st.SpaceTypeName, 
                s.Price,
                CASE 
                    WHEN i.Status = 'kicked' OR i.Flow_Status = 'done' THEN NULL 
                    ELSE c.Client_fn 
                END AS Client_fn,
                CASE 
                    WHEN i.Status = 'kicked' OR i.Flow_Status = 'done' THEN NULL 
                    ELSE c.Client_ln 
                END AS Client_ln
            FROM space s
            LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
            LEFT JOIN clientspace cs ON s.Space_ID = cs.Space_ID
            LEFT JOIN client c ON cs.Client_ID = c.Client_ID
            LEFT JOIN (
                SELECT inv1.*
                FROM invoice inv1
                INNER JOIN (
                    SELECT Client_ID, Space_ID, MAX(Created_At) AS max_created
                    FROM invoice
                    GROUP BY Client_ID, Space_ID
                ) inv2
                ON inv1.Client_ID = inv2.Client_ID 
                   AND inv1.Space_ID = inv2.Space_ID 
                   AND inv1.Created_At = inv2.max_created
            ) i ON i.Client_ID = c.Client_ID AND i.Space_ID = s.Space_ID
            ORDER BY s.Space_ID DESC";
    try {
        return $this->pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}


public function markRentalRequestDone($client_id, $space_id) {
    // Get the latest accepted rentalrequest for this client/space
    $latest = $this->runQuery(
        "SELECT Request_ID FROM rentalrequest 
         WHERE Client_ID = ? AND Space_ID = ? AND Status = 'Accepted'
         ORDER BY EndDate DESC LIMIT 1",
        [$client_id, $space_id]
    );
    if ($latest && isset($latest['Request_ID'])) {
        $this->executeStatement(
            "UPDATE rentalrequest SET Flow_Status = 'done' WHERE Request_ID = ?",
            [$latest['Request_ID']]
        );
    }
}




public function updateSpacePhotos($space_id, $new_photo_filename, $replace_index = null) {
    // Fetch current photo array
    $stmt = $this->pdo->prepare("SELECT Photo FROM space WHERE Space_ID = ?");
    $stmt->execute([$space_id]);
    $space = $stmt->fetch();
    $photos = [];
    if ($space && !empty($space['Photo'])) {
        $photos = json_decode($space['Photo'], true) ?: [];
    }

    // Add or replace photo
    if ($replace_index !== null && isset($photos[$replace_index])) {
        // Replace photo at index
        $photos[$replace_index] = $new_photo_filename;
    } else {
        // Append new photo
        $photos[] = $new_photo_filename;
    }

    // Save updated array as JSON
    $stmt = $this->pdo->prepare("UPDATE space SET Photo = ? WHERE Space_ID = ?");
    return $stmt->execute([json_encode($photos), $space_id]);
}



   public function rejectRentalRequest($request_id) {
    // Also mark as seen when rejecting
    $sql = "UPDATE rentalrequest SET Status = 'Rejected', admin_seen = 1 WHERE Request_ID = ? AND Status = 'Pending'";
    return $this->executeStatement($sql, [$request_id]);
}




    public function checkClientCredentialExists($field, $value) {
    if (!in_array($field, ['Client_Email', 'C_username'])) {
        return false;
    }

    $sql = "SELECT 1 FROM client WHERE {$field} = ? LIMIT 1";
    $result = $this->runQuery($sql, [$value]);
    
    // runQuery returns the fetched result directly, not a PDOStatement
    // If a record exists, $result will be an array; if not, it will be false
    return $result !== false;
}

    
public function getAllUnitsWithRenterStatus() {
    $sql = "SELECT s.Space_ID, s.Name, s.SpaceType_ID, s.Price, 
                   st.SpaceTypeName,
                   c.Client_ID, c.Client_fn, c.Client_ln, c.Client_Email,
                   CASE WHEN c.Client_ID IS NULL THEN 'Available' ELSE 'Rented' END as Status
            FROM space s
            LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
            LEFT JOIN clientspace cs ON s.Space_ID = cs.Space_ID
            LEFT JOIN client c ON cs.Client_ID = c.Client_ID";
    try {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}


public function getAdminMonthChartData($startDate, $endDate) {
    try {
        // Generate date range
        $dateRange = [];
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        
        while ($currentDate <= $endDateObj) {
            $dateRange[] = $currentDate->format('Y-m-d');
            $currentDate->modify('+1 day');
        }

        $chartData = [
            'labels' => [],
            'new_rentals' => [],
            'new_maintenance' => [],
            'new_messages' => []
        ];

        // Get data for each day
        foreach ($dateRange as $day) {
            $dayStart = $day . ' 00:00:00';
            $dayEnd = $day . ' 23:59:59';
            
            // Rentals for the day
            $sqlRentals = "SELECT COUNT(*) as count FROM rentalrequest 
                          WHERE Requested_At BETWEEN ? AND ?";
            $rentals = $this->getRow($sqlRentals, [$dayStart, $dayEnd]);
            
            // Maintenance for the day  
            $sqlMaintenance = "SELECT COUNT(*) as count FROM maintenancerequest 
                              WHERE RequestDate BETWEEN ? AND ?";
            $maintenance = $this->getRow($sqlMaintenance, [$dayStart, $dayEnd]);
            
            // Messages for the day
            $sqlMessages = "SELECT COUNT(*) as count FROM free_message 
                           WHERE is_deleted = 0 AND Sent_At BETWEEN ? AND ?";
            $messages = $this->getRow($sqlMessages, [$dayStart, $dayEnd]);

            $chartData['labels'][] = date('M j', strtotime($day));
            $chartData['new_rentals'][] = $rentals['count'] ?? 0;
            $chartData['new_maintenance'][] = $maintenance['count'] ?? 0;
            $chartData['new_messages'][] = $messages['count'] ?? 0;
        }

        return $chartData;

    } catch (Exception $e) {
        error_log("Error in getAdminMonthChartData: " . $e->getMessage());
        
        // Fallback: return empty data
        $days = [];
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        
        while ($currentDate <= $endDateObj) {
            $days[] = $currentDate->format('Y-m-d');
            $currentDate->modify('+1 day');
        }

        return [
            'labels' => array_map(function($d){ return date('M j', strtotime($d)); }, $days),
            'new_rentals' => array_fill(0, count($days), 0),
            'new_maintenance' => array_fill(0, count($days), 0),
            'new_messages' => array_fill(0, count($days), 0)
        ];
    }
}



 public function renameUnit($space_id, $new_name) {
        $sql = "UPDATE space SET Name = ? WHERE Space_ID = ?";
        return $this->executeStatement($sql, [$new_name, $space_id]);
    }

    // Get the latest invoice for a client/unit with Flow_Status = 'new'
    public function getLatestNewInvoiceForUnit($client_id, $space_id) {
        $sql = "SELECT * FROM invoice WHERE Client_ID = ? AND Space_ID = ? AND Flow_Status = 'new' ORDER BY EndDate DESC LIMIT 1";
        return $this->runQuery($sql, [$client_id, $space_id]);
    }

    
// NEW METHOD: Update invoice due date
public function updateInvoiceDueDate($invoice_id, $new_due_date) {
    $sql = "UPDATE invoice SET EndDate = ? WHERE Invoice_ID = ?";
    return $this->executeStatement($sql, [$new_due_date, $invoice_id]);
}

// NEW METHOD: Create next invoice with custom due date
public function createNextRecurringInvoiceWithChatCustomDate($invoice_id, $custom_due_date) {
    // Get the current invoice
    $invoice = $this->runQuery("SELECT * FROM invoice WHERE Invoice_ID = ?", [$invoice_id]);
    if (!$invoice) return false;
    
    // Calculate start date based on custom due date
    $start_date = date('Y-m-d', strtotime($invoice['InvoiceDate'] . ' +1 month'));
    $end_date = $custom_due_date;
    
    $this->pdo->beginTransaction();
    try {
        // Insert new invoice with custom end date
        $this->executeStatement(
            "INSERT INTO invoice (Client_ID, Space_ID, InvoiceDate, EndDate, InvoiceTotal, Status, Flow_Status) VALUES (?, ?, ?, ?, ?, 'unpaid', 'new')",
            [$invoice['Client_ID'], $invoice['Space_ID'], $start_date, $end_date, $invoice['InvoiceTotal']]
        );
        $new_invoice_id = $this->pdo->lastInsertId();
        
        // Copy all messages from old invoice chat
        $old_msgs = $this->runQueryAll(
            "SELECT * FROM invoice_chat WHERE Invoice_ID = ? ORDER BY Created_At ASC, Chat_ID ASC",
            [$invoice_id]
        );
        foreach ($old_msgs as $msg) {
            $this->executeStatement(
                "INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $new_invoice_id,
                    $msg['Sender_Type'],
                    $msg['Sender_ID'],
                    $msg['Message'],
                    $msg['Image_Path'],
                    $msg['Created_At']
                ]
            );
        }
        
        // Add system message
        $this->executeStatement(
            "INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Sender_ID, Message, Image_Path, Created_At) VALUES (?, 'system', NULL, ?, NULL, NOW())",
            [$new_invoice_id, 'Conversation continued from previous invoice with custom due date: ' . $custom_due_date]
        );
        
        $this->pdo->commit();
        return $new_invoice_id;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}













public function addJobTypeWithImage($jobTypeName, $iconFile) {
    try {
        // Handle file upload - AUTO CREATE DIRECTORY
        $uploadDir = __DIR__ . "/../uploads/jobtype_icons/";
        
        // Automatically create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = 'jobtype_' . time() . '_' . uniqid() . '.' . pathinfo($iconFile['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($iconFile['tmp_name'], $uploadPath)) {
            $sql = "INSERT INTO jobtype (JobType_Name, Icon) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$jobTypeName, $fileName]);
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error adding job type with image: " . $e->getMessage());
        return false;
    }
}



 public function deleteJobType($jobtype_id) {
    $this->pdo->beginTransaction();
    try {
        // Remove jobtype from handymanjob first to avoid foreign key issues
        $this->executeStatement("DELETE FROM handymanjob WHERE JobType_ID = ?", [$jobtype_id]);
        // Remove the jobtype itself
        $this->executeStatement("DELETE FROM jobtype WHERE JobType_ID = ?", [$jobtype_id]);
        $this->pdo->commit();
        return true;
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        error_log("Error deleting job type: " . $e->getMessage());
        return false;
    }
}


// Add this function to your Database class in database.php

public function getRentalRequestById($requestId) {
    $sql = "SELECT 
                rr.Request_ID,
                rr.Client_ID,
                rr.Space_ID,
                rr.StartDate,
                rr.EndDate,
                rr.Status,
                c.Client_fn,
                c.Client_ln,
                c.Client_Email,
                c.Client_Phone,
                s.Name AS SpaceName,
                s.Price,
                s.Street,
                s.Brgy,
                s.City
            FROM rentalrequest rr
            INNER JOIN client c ON rr.Client_ID = c.Client_ID
            INNER JOIN space s ON rr.Space_ID = s.Space_ID
            WHERE rr.Request_ID = ?";
    
    return $this->fetchSingle($sql, [$requestId]);
}

public function fetchSingle($sql, $params = []) {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Add these methods to your Database class in database/database.php

public function updateSpaceType($type_id, $new_name) {
    try {
        $sql = "UPDATE spacetype SET SpaceTypeName = ? WHERE SpaceType_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$new_name, $type_id]);
    } catch (PDOException $e) {
        error_log("Update Space Type Error: " . $e->getMessage());
        return false;
    }
}

public function deleteSpaceType($type_id) {
    try {
        // Check if any spaces are using this type
        $check_sql = "SELECT COUNT(*) FROM space WHERE SpaceType_ID = ?";
        $check_stmt = $this->pdo->prepare($check_sql);
        $check_stmt->execute([$type_id]);
        $count = $check_stmt->fetchColumn();
        
        if ($count > 0) {
            return false; // Cannot delete type that's in use
        }
        
        $sql = "DELETE FROM spacetype WHERE SpaceType_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$type_id]);
    } catch (PDOException $e) {
        error_log("Delete Space Type Error: " . $e->getMessage());
        return false;
    }
}
  public function updateSpace($space_id, $name, $spacetype_id, $price) {
    try {
        $sql = "UPDATE space SET Name = ?, SpaceType_ID = ?, Price = ? WHERE Space_ID = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$name, $spacetype_id, $price, $space_id]);
    } catch (PDOException $e) {
        error_log("Update Space Error: " . $e->getMessage());
        return false;
    }
}
// Add to your Database class
public function logPhotoAction($space_id, $photo_path, $action, $previous_photo_path = null, $action_by = null) {
    try {
        $sql = "INSERT INTO photo_history (Space_ID, Photo_Path, Action, Previous_Photo_Path, Action_By) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$space_id, $photo_path, $action, $previous_photo_path, $action_by]);
        return true;
    } catch (PDOException $e) {
        error_log("Photo history log error: " . $e->getMessage());
        return false;
    }
}

public function getPhotoHistory() {
    $sql = "SELECT ph.*, s.Name as Space_Name 
            FROM photo_history ph 
            LEFT JOIN space s ON ph.Space_ID = s.Space_ID 
            ORDER BY ph.Action_Date DESC";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getPhotoHistory Error: " . $e->getMessage());
        return [];
    }
}

public function getCurrentSpacePhotos($space_id) {
    $sql = "SELECT * FROM photo_history 
            WHERE Space_ID = ? AND Status = 'active' 
            ORDER BY Action_Date DESC";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$space_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getCurrentSpacePhotos Error: " . $e->getMessage());
        return [];
    }
}

public function getLatestMaintenanceRequests($limit = 5) {
    try {
        $sql = "SELECT mr.Request_ID, mr.RequestDate, mr.Status, mr.admin_seen,
                       c.Client_ID, c.Client_fn, c.Client_ln, c.Client_Email,
                       s.Name AS UnitName, s.Space_ID
                FROM maintenancerequest mr
                LEFT JOIN client c ON mr.Client_ID = c.Client_ID
                LEFT JOIN space s ON mr.Space_ID = s.Space_ID
                WHERE mr.Status IN ('Submitted', 'In Progress')
                ORDER BY mr.RequestDate DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting latest maintenance requests: " . $e->getMessage());
        return [];
    }
}


// Update photo description
    public function updatePhotoDescription($history_id, $description) {
        $sql = "UPDATE photo_history SET description = ? WHERE History_ID = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$description, $history_id]);
        } catch (PDOException $e) {
            error_log("updatePhotoDescription Error: " . $e->getMessage());
            return false;
        }
    }

// Get photo with description
   public function getPhotoWithDescription($history_id) {
        $sql = "SELECT * FROM photo_history WHERE History_ID = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$history_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPhotoWithDescription Error: " . $e->getMessage());
            return null;
        }
    }






public function getActivePhotosForUnits($unit_ids) {
    if (empty($unit_ids)) return [];
    
    $placeholders = implode(',', array_fill(0, count($unit_ids), '?'));
    $sql = "SELECT Space_ID, Photo_Path, Status 
            FROM photo_history 
            WHERE Space_ID IN ($placeholders) 
            AND Status = 'active'
            ORDER BY Action_Date DESC";
    
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($unit_ids);
        
        $photos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($photos[$row['Space_ID']])) {
                $photos[$row['Space_ID']] = [];
            }
            $photos[$row['Space_ID']][] = $row;
        }
        return $photos;
    } catch (PDOException $e) {
        error_log("getActivePhotosForUnits Error: " . $e->getMessage());
        return [];
    }
}

public function addPhotoToHistory($space_id, $filename, $action, $previous_filename = null, $admin_id = null, $description = null) {
    $sql = "INSERT INTO photo_history (Space_ID, Photo_Path, Action, Previous_Photo_Path, Action_By, Status, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $status = ($action === 'deleted') ? 'inactive' : 'active';
    try {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$space_id, $filename, $action, $previous_filename, $admin_id, $status, $description]);
    } catch (PDOException $e) {
        error_log("addPhotoToHistory Error: " . $e->getMessage());
        return false;
    }
}

public function deactivatePhoto($space_id, $filename) {
    $sql = "UPDATE photo_history SET Status = 'inactive' 
            WHERE Space_ID = ? AND Photo_Path = ? AND Status = 'active'";
    try {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$space_id, $filename]);
    } catch (PDOException $e) {
        error_log("deactivatePhoto Error: " . $e->getMessage());
        return false;
    }
}

public function getSpaceName($space_id) {
    $sql = "SELECT Name FROM space WHERE Space_ID = ?";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$space_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['Name'] : 'Unknown Space';
    } catch (PDOException $e) {
        error_log("getSpaceName Error: " . $e->getMessage());
        return 'Unknown Space';
    }
}



}




?>


