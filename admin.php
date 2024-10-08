<?php
session_start();
include './includes/config.php';

if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header('Location: ./auth/admin_login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $transaction_id = $_POST['transaction_id'];
    $status = $_POST['status'];
    $current_status = $_POST['current_status'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Update the status in the orders table
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND transaction_id = ?");
        $stmt->bind_param('sss', $status, $order_id, $transaction_id);
        $stmt->execute();
        $stmt->close();

        // Update the status in the user_purchases table
        $stmt = $conn->prepare("UPDATE user_purchases SET status = ? WHERE order_id = ?");
        $stmt->bind_param('ss', $status, $order_id);
        $stmt->execute();
        $stmt->close();

        if ($status === 'failed') {
            // Delete the corresponding row from the user_purchases table
            $stmt = $conn->prepare("DELETE FROM user_purchases WHERE order_id = ?");
            $stmt->bind_param('s', $order_id);
            $stmt->execute();
            $stmt->close();
        }

        // If everything is successful, commit the transaction
        $conn->commit();
        $message = 'Order status updated successfully in both orders and user_purchases tables.';
    } catch (Exception $e) {
        // If there's an error, roll back the changes
        $conn->rollback();
        $message = 'Error occurred: ' . $e->getMessage();
    }
}

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'o.order_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Fetch orders with user details
$sql = "SELECT o.id AS order_id, o.order_id AS order_identifier, o.amount, o.status, o.transaction_id, 
               o.order_date, u.username, u.phone, u.college, GROUP_CONCAT(e.title SEPARATOR ', ') AS event_names 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN events e ON oi.event_id = e.id
        WHERE o.order_id LIKE '%$search%' 
           OR u.username LIKE '%$search%' 
           OR u.phone LIKE '%$search%' 
           OR u.college LIKE '%$search%'
           OR e.title LIKE '%$search%'
        GROUP BY o.id
        ORDER BY $sort $order";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advaita Admin Dashboard</title>
    <link rel="stylesheet" href="styles/admin.css">
</head>

<body>
    <header>
        <nav>
            <div class="nav-brand">Advaita</div>
            <ul>
                <li><a href="./admin_dashboard.php">Dashboard</a></li>
                <li><a href="./auth/admin_logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <h1>Admin - Transaction Confirmation Dashboard</h1>
        <?php if (!empty($message)) : ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form class="search-form" method="get">
            <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th><a href="?sort=o.order_date&order=<?php echo $sort === 'o.order_date' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Order Date</a></th>
                    <th><a href="?sort=o.order_id&order=<?php echo $sort === 'o.order_id' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Order ID</a></th>
                    <th><a href="?sort=u.username&order=<?php echo $sort === 'u.username' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Username</a></th>
                    <th><a href="?sort=u.phone&order=<?php echo $sort === 'u.phone' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Phone</a></th>
                    <th><a href="?sort=u.college&order=<?php echo $sort === 'u.college' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">College</a></th>
                    <th>Events</th>
                    <th><a href="?sort=o.amount&order=<?php echo $sort === 'o.amount' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Amount</a></th>
                    <th><a href="?sort=o.status&order=<?php echo $sort === 'o.status' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Status</a></th>
                    <th>Transaction ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($row['order_date'])); ?></td>
                        <td><?php echo $row['order_identifier']; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['phone']; ?></td>
                        <td><?php echo $row['college']; ?></td>
                        <td><?php echo $row['event_names']; ?></td>
                        <td><?php echo $row['amount']; ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['transaction_id']; ?></td>
                        <td>
                            <form method="post" class="update-form">
                                <input type="hidden" name="order_id" value="<?php echo $row['order_identifier']; ?>">
                                <input type="hidden" name="transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $row['status']; ?>">
                                <select name="status" required>
                                    <option value="pending" <?php echo $row['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $row['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="failed" <?php echo $row['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/admin-dashboard.js"></script>
</body>

</html>

<?php
$conn->close();
?>