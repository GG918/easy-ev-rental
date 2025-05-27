<?php
// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['vehicle_id'], $_POST['description'], $_POST['maintenance_date'])) {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Connect to the database
    $conn = new mysqli("localhost", "root", "", "ev_rental_db");

    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        $vehicle_id = intval($_POST['vehicle_id']); // Ensure it's an integer
        $description = trim($_POST['description']);
        $maintenance_date = $_POST['maintenance_date']; // Should be in YYYY-MM-DD format

        // Prepare SQL statement
        $stmt = $conn->prepare("INSERT INTO maintenances (vehicle_id, description, maintenance_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $vehicle_id, $description, $maintenance_date);

        // Execute query and check for errors
        if ($stmt->execute()) {
            $success = "Maintenance logged successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }

        // Close resources
        $stmt->close();
        $conn->close();
    }
    
    // If it's an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        if (isset($error)) {
            echo json_encode(['success' => false, 'message' => $error]);
        } else {
            echo json_encode(['success' => true, 'message' => $success]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Maintenance</title>
    <link rel="stylesheet" href="css/maintenance.css">
</head>
<body>
    <div class="container">
        <h2>Log Maintenance</h2>
        <?php if (isset($success)): ?>
        <div class="result-message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="result-message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="maintenanceForm" method="POST">
            <input type="number" name="vehicle_id" placeholder="Vehicle ID" required>
            <textarea name="description" placeholder="Maintenance Details" required></textarea>
            <input type="date" name="maintenance_date" required>
            <button type="submit">Log Maintenance</button>
        </form>
    </div>
    
    <script>
        // When form is submitted using AJAX
        document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
            // If called from modal, don't prevent default submission
            if (window.location.pathname.endsWith('maintenance.php')) {
                // Only use AJAX when directly accessing maintenance.php page
                e.preventDefault();
                
                const form = this;
                const formData = new FormData(form);
                
                fetch('maintenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    let resultElement = document.querySelector('.result-message');
                    
                    if (!resultElement) {
                        resultElement = document.createElement('div');
                        resultElement.className = 'result-message';
                        form.parentNode.insertBefore(resultElement, form);
                    }
                    
                    if (data.success) {
                        resultElement.className = 'result-message success';
                        resultElement.textContent = data.message;
                        form.reset();
                    } else {
                        resultElement.className = 'result-message error';
                        resultElement.textContent = data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting the form');
                });
            }
        });
    </script>
</body>
</html>

