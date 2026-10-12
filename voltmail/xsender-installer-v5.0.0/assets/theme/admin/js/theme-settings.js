function handleThemeCardClick() {
        const themeData = {
            slug: this.dataset.theme,
            name: this.dataset.name,
            description: this.dataset.description,
            version: this.dataset.version,
            features: JSON.parse(this.dataset.features),
            screenshot_urls: JSON.parse(this.dataset.screenshots)
        };
        
        currentIndex = 0;
        currentThemeData = themeData;
        carouselOffset = 0;
        
        document.getElementById('selectedTheme').value = themeData.slug;
        document.getElementById('modalTitle').textContent = themeData.name;
        document.getElementById('modalDescription').textContent = themeData.description;
        
        updateModal(themeData);
    }

    function updateModal(themeData) {
        updatePreview(themeData);
        updateThumbnails(themeData);
        updateFeatures(themeData);
        updateInfo(themeData);
        updateButton(themeData);
        updateCarouselNavigation(themeData);
    }

    function updatePreview(themeData) {
        const screenshot = themeData.screenshot_urls[currentIndex];
        const mainImage = document.getElementById('mainPreviewImage');
        mainImage.src = screenshot?.preview;
        document.getElementById('imageCounter').textContent = `${currentIndex + 1}/${themeData.screenshot_urls.length}`;
    }

    function updateThumbnails(themeData) {
        const container = document.getElementById('thumbnailCarousel');
        container.innerHTML = '';
        
        themeData.screenshot_urls.forEach((screenshot, index) => {
            const thumb = document.createElement('div');
            thumb.className = `thumbnail-item ${index === currentIndex ? 'active' : ''}`;
            thumb.setAttribute('role', 'button');
            thumb.setAttribute('tabindex', '0');
            thumb.setAttribute('aria-label', `Preview ${index + 1}`);
            
            const img = document.createElement('img');
            img.src = screenshot.thumbnail;
            img.className = 'w-100 h-100 object-fit-cover';
            img.alt = `Preview ${index + 1}`;
            
            thumb.appendChild(img);
            
            const selectThumbnail = () => {
                currentIndex = index;
                updateModal(themeData);
            };
            
            thumb.addEventListener('click', selectThumbnail);
            thumb.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectThumbnail();
                }
            });
            
            container.appendChild(thumb);
        });
        
        updateCarouselPosition();
    }

    function navigateCarousel(direction) {
        if (!currentThemeData) return;
        
        const totalThumbnails = currentThemeData.screenshot_urls.length;
        const maxOffset = Math.max(0, totalThumbnails - thumbnailsPerView);
        
        if (direction === 'prev' && carouselOffset > 0) {
            carouselOffset--;
        } else if (direction === 'next' && carouselOffset < maxOffset) {
            carouselOffset++;
        }
        
        updateCarouselPosition();
        updateCarouselNavigation(currentThemeData);
    }

    function updateCarouselPosition() {
        const carousel = document.getElementById('thumbnailCarousel');
        const container = document.querySelector('.thumbnail-carousel-container');
        const containerWidth = container.offsetWidth - 90; 
        const thumbnailWidth = 80 + 12;
        const translateX = carouselOffset * thumbnailWidth;
        
        const maxTranslate = Math.max(0, (carousel.children.length * thumbnailWidth) - containerWidth);
        const safeTranslateX = Math.min(translateX, maxTranslate);
        
        carousel.style.transform = `translateX(-${safeTranslateX}px)`;
    }

    function updateCarouselNavigation(themeData) {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const totalThumbnails = themeData.screenshot_urls.length;
        const container = document.querySelector('.thumbnail-carousel-container');
        const containerWidth = container.offsetWidth - 90; 
        const thumbnailWidth = 80 + 12; 
        
        const thumbnailsVisible = Math.floor(containerWidth / thumbnailWidth);
        const maxOffset = Math.max(0, totalThumbnails - thumbnailsVisible);
        
        prevBtn.disabled = carouselOffset === 0;
        nextBtn.disabled = carouselOffset >= maxOffset;
        
        if (totalThumbnails <= thumbnailsVisible) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        } else {
            prevBtn.style.display = 'flex';
            nextBtn.style.display = 'flex';
        }
    }

    function updateFeatures(themeData) {
        const container = document.getElementById('featuresList');
        container.innerHTML = '';
        
        if (themeData.features && themeData.features.length > 0) {
            themeData.features.forEach(feature => {
                const badge = document.createElement('span');
                badge.className = 'feature-badge';
                badge.innerHTML = `<i class="ri-check-line"></i>${feature}`;
                container.appendChild(badge);
            });
        } else {
            container.innerHTML = '<span class="text-muted small">{{ translate("No features listed") }}</span>';
        }
    }

    function updateInfo(themeData) {
        const infoContainer = document.getElementById('themeInfo');
        infoContainer.innerHTML = `
            <div class="mb-2">${themeData.name || 'N/A'}</div>
            <div class="mb-2">v${themeData.version || 'N/A'}</div>
        `;
    }