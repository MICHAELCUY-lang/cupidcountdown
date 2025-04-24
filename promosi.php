<!-- Tambahkan kode berikut pada file dashboard.php di bagian akhir sebelum tag </body> -->

<?php
// Fetch random active promotion to display as popup
// Add this query after all other queries in the PHP section
$popup_promo_sql = "SELECT p.*, u.name as user_name 
                    FROM promotions p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.status = 'active' 
                    AND p.id NOT IN (
                        SELECT promotion_id FROM promotion_dismissals 
                        WHERE user_id = ? AND DATE(dismissed_at) = CURDATE()
                    ) 
                    ORDER BY RAND() LIMIT 1";
$popup_promo_stmt = $conn->prepare($popup_promo_sql);
$popup_promo_stmt->bind_param("i", $user_id);
$popup_promo_stmt->execute();
$popup_promo_result = $popup_promo_stmt->get_result();
$popup_promo = $popup_promo_result->fetch_assoc();

// Update impressions counter if we found a promotion to display
if ($popup_promo) {
    $update_impression_sql = "UPDATE promotions SET impressions = impressions + 1 WHERE id = ?";
    $update_impression_stmt = $conn->prepare($update_impression_sql);
    $update_impression_stmt->bind_param("i", $popup_promo['id']);
    $update_impression_stmt->execute();
}
?>

<!-- Promotion Popup HTML -->
<?php if ($popup_promo): ?>
<div id="promotion-popup" class="promotion-popup">
    <div class="promotion-popup-content">
        <button class="promotion-popup-close" id="close-promotion" data-promo-id="<?php echo $popup_promo['id']; ?>">
            <i class="fas fa-times"></i>
        </button>
        <div class="promotion-popup-image">
            <img src="<?php echo htmlspecialchars($popup_promo['image_url']); ?>" alt="<?php echo htmlspecialchars($popup_promo['title']); ?>">
            <div class="promotion-popup-badge">Promosi</div>
        </div>
        <div class="promotion-popup-info">
            <h3><?php echo htmlspecialchars($popup_promo['title']); ?></h3>
            <p><?php echo htmlspecialchars($popup_promo['description']); ?></p>
            <div class="promotion-popup-meta">
                <span class="promotion-popup-author">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($popup_promo['user_name']); ?>
                </span>
            </div>
            <a href="<?php echo htmlspecialchars($popup_promo['target_url']); ?>" class="btn btn-block promotion-popup-btn" target="_blank" data-promo-id="<?php echo $popup_promo['id']; ?>">
                <i class="fas fa-external-link-alt"></i> Kunjungi Link
            </a>
            <div class="promotion-popup-footer">
                <a href="dashboard?page=promotion" class="promotion-popup-link">Buat promosi sendiri?</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CSS for Promotion Popup -->
<style>
/* Promotion Popup Styling */
.promotion-popup {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    width: 350px;
    background-color: var(--card-bg);
    border-radius: 10px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    transform: translateY(100%);
    opacity: 0;
    animation: slideIn 0.5s ease forwards;
    animation-delay: 2s;
}

@keyframes slideIn {
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.promotion-popup-close {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.6);
    color: white;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.promotion-popup-close:hover {
    background-color: rgba(0, 0, 0, 0.8);
}

.promotion-popup-image {
    height: 180px;
    position: relative;
    overflow: hidden;
}

.promotion-popup-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.promotion-popup:hover .promotion-popup-image img {
    transform: scale(1.05);
}

.promotion-popup-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background-color: var(--primary);
    color: white;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 20px;
}

.promotion-popup-info {
    padding: 20px;
}

.promotion-popup-info h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--text-color);
}

.promotion-popup-info p {
    color: #666;
    margin-bottom: 15px;
    font-size: 14px;
    line-height: 1.5;
}

.promotion-popup-meta {
    margin-bottom: 15px;
    font-size: 13px;
    color: #666;
}

.promotion-popup-meta i {
    color: var(--primary);
    margin-right: 5px;
}

.promotion-popup-btn {
    margin-bottom: 10px;
}

.promotion-popup-footer {
    text-align: center;
    font-size: 13px;
}

.promotion-popup-link {
    color: var(--primary);
    text-decoration: none;
}

.promotion-popup-link:hover {
    text-decoration: underline;
}

@media (max-width: 480px) {
    .promotion-popup {
        width: calc(100% - 40px);
    }
}
</style>

<!-- JavaScript for Promotion Popup -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const promotionPopup = document.getElementById('promotion-popup');
    const closePromotion = document.getElementById('close-promotion');
    
    if (closePromotion) {
        closePromotion.addEventListener('click', function() {
            const promoId = this.getAttribute('data-promo-id');
            
            // Hide popup
            promotionPopup.style.animation = 'none';
            promotionPopup.style.transform = 'translateY(100%)';
            promotionPopup.style.opacity = '0';
            
            // Record dismissal in database
            fetch('dismiss_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'promo_id=' + promoId
            });
            
            // Remove from DOM after animation
            setTimeout(() => {
                promotionPopup.remove();
            }, 500);
        });
    }
    
    // Track clicks on promotion link
    const promoLink = document.querySelector('.promotion-popup-btn');
    if (promoLink) {
        promoLink.addEventListener('click', function() {
            const promoId = this.getAttribute('data-promo-id');
            
            // Record click in database
            fetch('record_promotion_click.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'promo_id=' + promoId
            });
        });
    }
});
</script>



