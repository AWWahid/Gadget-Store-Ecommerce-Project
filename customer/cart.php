
<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// ------------------------------
// CHECK CUSTOMER AUTHENTICATION
// ------------------------------
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$customer_id = $_SESSION['user_id'];

// ------------------------------
// HANDLE CART UPDATE/REMOVE
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        foreach ($_POST['quantities'] as $cart_id => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity > 0) {
                $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?")
                    ->execute([$quantity, $cart_id, $customer_id]);
            } else {
                $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?")
                    ->execute([$cart_id, $customer_id]);
            }
        }
    } elseif (isset($_POST['remove'])) {
        $cart_id = (int)$_POST['cart_id'];
        $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?")
            ->execute([$cart_id, $customer_id]);
    }

    // reload page after update/remove
    redirect('cart.php');
}

// ------------------------------
// GET CART ITEMS
// ------------------------------
$sql = "SELECT c.cart_id, c.quantity, p.*, b.brand_name, pi.image_url 
        FROM cart c 
        JOIN product p ON c.product_id = p.product_id 
        LEFT JOIN brand b ON p.brand_id = b.brand_id 
        LEFT JOIN product_image pi ON p.product_id = pi.product_id 
        WHERE c.customer_id = ? 
        GROUP BY p.product_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll();

// ------------------------------
// CALCULATE TOTALS
// ------------------------------
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.10; // 10%
$shipping = count($cart_items) > 0 ? 5.00 : 0;
$total = $subtotal + $tax + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart - Gadget Store</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Gadget Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php
require("navbar.php");
?>

<div class="container mt-4">
    <h1 class="mb-4">Shopping Cart</h1>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            Your cart is empty. <a href="../index.php">Continue shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- CART ITEMS -->
            <div class="col-lg-8">
                <form method="POST" action="">
                    <div class="card mb-4">
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="row mb-3 pb-3 border-bottom">
                                <div class="col-md-2">
                                    <img src="<?php echo $item['image_url'] ?? 'https://via.placeholder.com/100'; ?>" 
                                         alt="<?php echo $item['product_name']; ?>" 
                                         class="img-fluid rounded">
                                </div>
                                <div class="col-md-6">
                                    <h5><?php echo $item['product_name']; ?></h5>
                                    <p class="text-muted"><?php echo $item['brand_name']; ?></p>
                                    <p class="text-success">$<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="quantities[<?php echo $item['cart_id']; ?>]" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="10" class="form-control">
                                </div>
                                <div class="col-md-2">
                                    <p class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                    <button type="submit" name="remove" value="1" class="btn btn-sm btn-outline-danger">
                                        Remove
                                    </button>
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between">
                                <a href="../index.php" class="btn btn-outline-secondary">
                                    Continue Shopping
                                </a>
                                <button type="submit" name="update" value="1" class="btn btn-primary">
                                    Update Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ORDER SUMMARY & CHECKOUT -->
            <div class="col-lg-4">
                <form method="POST" action="checkout.php">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span>$<?php echo number_format($shipping, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (10%)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total</strong>
                                <strong>$<?php echo number_format($total, 2); ?></strong>
                            </div>

                            <!-- SHIPPING ADDRESS -->
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label fw-bold">Shipping Address</label>
                                <textarea name="shipping_address" id="shipping_address" class="form-control" rows="3" required></textarea>
                            </div>

                            <!-- PAYMENT METHOD -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cod" value="Cash on Delivery" checked>
                                    <label class="form-check-label" for="cod">Cash on Delivery</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bkash" value="Bkash">
                                    <label class="form-check-label" for="bkash">Bkash</label>
                                </div>
                            </div>

                            <!-- BKASH TRANSACTION ID -->
                            <div class="mb-3" id="transaction-field" style="display: none;">
                                <label for="transaction_id" class="form-label fw-bold">Bkash Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_id" id="transaction_id" placeholder="Enter Bkash transaction ID">
                            </div>

                            <input type="hidden" name="total_amount" value="<?php echo $total; ?>">

                            <button type="submit" class="btn btn-success w-100">Proceed to Checkout</button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- SHOW/HIDE BKASH TRANSACTION FIELD -->
<script>
const codRadio = document.getElementById('cod');
const bkashRadio = document.getElementById('bkash');
const transactionField = document.getElementById('transaction-field');

function toggleTransactionField() {
    transactionField.style.display = bkashRadio.checked ? 'block' : 'none';
}

codRadio.addEventListener('change', toggleTransactionField);
bkashRadio.addEventListener('change', toggleTransactionField);
toggleTransactionField();
</script>

</body>
</html>