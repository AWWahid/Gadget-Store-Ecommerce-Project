<?php
session_start();
require_once "discount.php";

// Init discount session if not set
if (!isset($_SESSION['applied_discount'])) {
    $_SESSION['applied_discount'] = null;
}

// -----------------------------------------------------------
// FETCH CART ITEMS
// -----------------------------------------------------------
$cart_items = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(",", array_keys($_SESSION['cart']));
    $query = "SELECT * FROM products WHERE product_id IN ($ids)";
    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $row['quantity'] = $_SESSION['cart'][$row['product_id']];
        $row['total_price'] = $row['price'] * $row['quantity'];
        $subtotal += $row['total_price'];
        $cart_items[] = $row;
    }
}


// -----------------------------------------------------------
// APPLY DISCOUNT LOGIC (same logic used in discount.php)
// -----------------------------------------------------------
function applyDiscount($subtotal, $discount)
{
    $type = $discount['type'];
    $value = floatval($discount['value']);

    if ($type === "percentage") {
        // ex: 10% off
        $discountAmount = ($subtotal * $value) / 100;
        return max(0, $subtotal - $discountAmount);

    } elseif ($type === "fixed") {
        // ex: 200 taka off
        return max(0, $subtotal - $value);
    }

    return $subtotal;
}


// -----------------------------------------------------------
// WHEN APPLY DISCOUNT BUTTON IS PRESSED
// -----------------------------------------------------------
$discount_error = "";
if (isset($_POST['apply_discount'])) {
    $code = mysqli_real_escape_string($conn, $_POST['discount_code']);

    $sql = "SELECT * FROM discount WHERE discount_code = '$code' LIMIT 1";
    $res = mysqli_query($conn, $sql);

    if ($res && mysqli_num_rows($res) == 1) {
        $discount = mysqli_fetch_assoc($res);

        // 1. Active check
        if ($discount['is_active'] != 1) {
            $discount_error = "This discount code is not active.";
        }
        // 2. Date check
        elseif (date("Y-m-d") < $discount['start_date'] ||
                date("Y-m-d") > $discount['expiry_date']) {
            $discount_error = "This discount code is expired or not yet valid.";
        }
        else {
            // 3. CHECK APPLICABILITY
            $isApplicable = false;

            if ($discount['applicable_to'] == "all") {
                $isApplicable = true;
            }
            elseif ($discount['applicable_to'] == "category") {

                // check if any product in cart matches discount category
                foreach ($cart_items as $item) {
                    if ($item['category_id'] == $discount['category_id']) {
                        $isApplicable = true;
                        break;
                    }
                }
            }

            if (!$isApplicable) {
                $discount_error = "This discount is not applicable to items in your cart.";
            } else {
                // SUCCESS: Store discount in session
                $_SESSION['applied_discount'] = $discount;
            }
        }

    } else {
        $discount_error = "Invalid discount code.";
    }
}


// -----------------------------------------------------------
// CALCULATE FINAL TOTAL AFTER DISCOUNT
// -----------------------------------------------------------
$final_total = $subtotal;
if ($_SESSION['applied_discount']) {
    $final_total = applyDiscount($subtotal, $_SESSION['applied_discount']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Cart</title>
    <style>
        body {font-family: Arial; padding: 20px; background:#f5f6fa;}
        table {width:100%; border-collapse: collapse; background:#fff;}
        th,td {padding:10px; border:1px solid #ccc; text-align:center;}
        th {background:#2d3436; color:#fff;}
        .apply-btn {padding:5px 10px; background:#0984e3; color:#fff; border:none; border-radius:4px; cursor:pointer;}
        .error {color:#d63031; font-weight:bold;}
        .success {color:#00b894; font-weight:bold;}
    </style>
</head>

<body>
<h2>Your Cart</h2>

<table>
    <tr>
        <th>Product</th>
        <th>Category</th>
        <th>Unit Price</th>
        <th>Qty</th>
        <th>Total</th>
    </tr>

    <?php foreach ($cart_items as $item): ?>
        <tr>
            <td><?= $item['name'] ?></td>
            <td><?= $item['category_id'] ?></td>
            <td><?= $item['price'] ?> Taka</td>
            <td><?= $item['quantity'] ?></td>
            <td><?= $item['total_price'] ?> Taka</td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Subtotal: <?= $subtotal ?> Taka</h3>

<!-- DISCOUNT BOX -->
<form method="POST">
    <input type="text" name="discount_code" placeholder="Enter discount code" required>
    <button class="apply-btn" name="apply_discount">Apply</button>
</form>

<?php if ($discount_error): ?>
    <p class="error"><?= $discount_error ?></p>
<?php elseif ($_SESSION['applied_discount']): ?>
    <p class="success">Discount Applied: 
        <?= $_SESSION['applied_discount']['discount_code'] ?> 
        (<?= $_SESSION['applied_discount']['type'] ?>)
    </p>
<?php endif; ?>

<h2>Final Total: <?= $final_total ?> Taka</h2>

</body>
</html>
