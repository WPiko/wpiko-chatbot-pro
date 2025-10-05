document.addEventListener('DOMContentLoaded', function() {
    // Date range handling
    const dateRangeSelect = document.getElementById('date_range');
    const customDateInputs = document.getElementById('custom-date-inputs');
    const analyticsForm = document.getElementById('analytics-date-range');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    if (dateRangeSelect) {
        // Handle date range selection changes
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateInputs.style.display = 'flex';
            } else {
                customDateInputs.style.display = 'none';
                analyticsForm.submit();
            }
        });

        if (startDate && endDate) {
            // Set min/max constraints for date inputs
            startDate.max = endDate.value;
            endDate.min = startDate.value;

            startDate.addEventListener('change', function() {
                endDate.min = this.value;
                if (endDate.value && this.value <= endDate.value) {
                    analyticsForm.submit();
                }
            });

            endDate.addEventListener('change', function() {
                startDate.max = this.value;
                if (startDate.value && this.value >= startDate.value) {
                    analyticsForm.submit();
                }
            });
        }
    }

    // Location view toggle handling
    const locationToggleBtns = document.querySelectorAll('.location-toggle-btn');
    
    locationToggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const selectedView = this.getAttribute('data-view');
            
            // Add loading state
            const locationsCard = this.closest('.analytics-card');
            if (locationsCard) {
                locationsCard.classList.add('loading');
            }
            
            // Update URL with location view parameter
            const url = new URL(window.location);
            url.searchParams.set('location_view', selectedView);
            
            // Keep existing date range parameters
            if (dateRangeSelect && dateRangeSelect.value) {
                url.searchParams.set('date_range', dateRangeSelect.value);
            }
            if (startDate && startDate.value) {
                url.searchParams.set('start_date', startDate.value);
            }
            if (endDate && endDate.value) {
                url.searchParams.set('end_date', endDate.value);
            }
            
            // Add nonce to the URL
            const nonceField = document.querySelector('input[name="wpiko_analytics_nonce"]');
            if (nonceField) {
                url.searchParams.set('wpiko_analytics_nonce', nonceField.value);
            }
            
            // Navigate to the new URL
            window.location.href = url.toString();
        });
    });

    // Handle chart dot hover effects
    const chartDots = document.querySelectorAll('.chart-dot');
    const chartArea = document.querySelector('.chart-area');

    if (chartArea) {
        // Create vertical line element
        const verticalLine = document.createElement('div');
        verticalLine.className = 'vertical-line';
        chartArea.appendChild(verticalLine);

        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'chart-tooltip';
        chartArea.appendChild(tooltip);

        chartDots.forEach(dot => {
            dot.removeAttribute('title');
            
            dot.addEventListener('mouseenter', function(e) {
                const value = this.getAttribute('data-value');
                const date = this.getAttribute('data-date');
                const dotPosition = this.style.left;

                // Show and position vertical line
                verticalLine.style.left = dotPosition;
                verticalLine.style.display = 'block';

                // Show and position tooltip
                tooltip.style.left = dotPosition;
                tooltip.style.display = 'block';
                tooltip.innerHTML = `${date}: ${value} messages`;
                tooltip.style.transform = 'translateX(-50%)';
                tooltip.style.top = '0';
                
                // Prevent default tooltip
                e.preventDefault();
            });

            dot.addEventListener('mouseleave', function() {
                verticalLine.style.display = 'none';
                tooltip.style.display = 'none';
            });
        });
    }
});