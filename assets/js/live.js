/* Extracted from live.php inline <script> (refactor). */

        document.addEventListener('DOMContentLoaded', function() {
            // Mobile hamburger menu functionality
            const hamburgerMenu = document.getElementById('hamburger-menu');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (hamburgerMenu && sidebar && sidebarOverlay) {
                // Toggle sidebar on hamburger click
                hamburgerMenu.addEventListener('click', function() {
                    hamburgerMenu.classList.toggle('active');
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    
                    // Prevent body scrolling when sidebar is open
                    if (sidebar.classList.contains('active')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
                
                // Close sidebar when clicking on overlay
                sidebarOverlay.addEventListener('click', function() {
                    hamburgerMenu.classList.remove('active');
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
                
                // Close sidebar on window resize if mobile view is exited
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        hamburgerMenu.classList.remove('active');
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // Enhanced animated food items
            const animatedBg = document.querySelector('.animated-bg');
            const foodItems = [
                '🍕', '🍔', '🍟', '🌮', '🍣', '🍜', '🍱', '🥗', '🍲', 
                '🍝', '🍛', '🍤', '🍗', '🥩', '🍖', '🧁', '🍰', '🍪',
                '🥘', '🍳', '🥞', '🧇', '🥯', '🍞', '🥖', '🥨', '🧀',
                '🥑', '🍅', '🥕', '🌶️', '🫒', '🥬', '🥒', '🌽'
            ];
            
            // Create initial food items
            for (let i = 0; i < 30; i++) {
                setTimeout(() => createFoodItem(), i * 500);
            }
            
            // Continue creating food items at intervals
            setInterval(createFoodItem, 1500);
            
            function createFoodItem() {
                const food = document.createElement('div');
                food.className = 'food-item';
                
                // Random food emoji
                const randomFood = foodItems[Math.floor(Math.random() * foodItems.length)];
                food.textContent = randomFood;
                
                // Random properties for more variety
                const size = Math.floor(Math.random() * 35) + 25; // 25-60px
                const left = Math.floor(Math.random() * 100); // 0-100%
                const animationDuration = Math.floor(Math.random() * 12) + 12; // 12-24s
                const delay = Math.floor(Math.random() * 8); // 0-8s
                const opacity = (Math.random() * 0.4) + 0.6; // 0.6-1.0
                
                food.style.cssText = `
                    left: ${left}%;
                    font-size: ${size}px;
                    animation-duration: ${animationDuration}s;
                    animation-delay: ${delay}s;
                    --max-opacity: ${opacity};
                `;
                
                animatedBg.appendChild(food);
                
                // Remove food item after animation completes
                setTimeout(() => {
                    if (food.parentNode === animatedBg) {
                        animatedBg.removeChild(food);
                    }
                }, (animationDuration + delay) * 1000);
            }
            
            // Add parallax effect to glass card
            const glassCard = document.querySelector('.glass-card');
            
            document.addEventListener('mousemove', function(e) {
                if (window.innerWidth > 768) {
                    const { clientX, clientY } = e;
                    const { innerWidth, innerHeight } = window;
                    
                    const xPercent = (clientX / innerWidth - 0.5) * 2;
                    const yPercent = (clientY / innerHeight - 0.5) * 2;
                    
                    glassCard.style.transform = `
                        perspective(1000px) 
                        rotateY(${xPercent * 2}deg) 
                        rotateX(${-yPercent * 2}deg)
                        translateZ(0)
                    `;
                }
            });
            
            // Reset transform on mouse leave
            document.addEventListener('mouseleave', function() {
                glassCard.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) translateZ(0)';
            });
        });
    
