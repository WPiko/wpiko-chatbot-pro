document.addEventListener('DOMContentLoaded', function() {
    const dateRangeSelect = document.getElementById('date_range');
    const customDateInputs = document.getElementById('custom-date-inputs');
    const analyticsForm = document.getElementById('analytics-date-range');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateInputs.style.display = 'flex';
            } else {
                customDateInputs.style.display = 'none';
                analyticsForm.submit();
            }
        });

        if (startDate && endDate) {
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

    const analyticsFilters = {
        dateRangeSelect: dateRangeSelect,
        analyticsForm: analyticsForm,
        startDate: startDate,
        endDate: endDate
    };
    const locationAnalytics = initializeLocationAnalytics(analyticsFilters);

    const messageChart = document.querySelector('[data-message-activity-chart]');
    const dailyMessages = window.analyticsData && Array.isArray(window.analyticsData.dailyMessages)
        ? window.analyticsData.dailyMessages
        : [];
    let messageActivityChart = null;

    if (messageChart) {
        messageActivityChart = initializeMessageActivityChart(messageChart, dailyMessages);
    }

    initializeAnalyticsLiveUpdates({
        filters: analyticsFilters,
        locationAnalytics: locationAnalytics,
        messageActivityChart: messageActivityChart
    });
});

function initializeLocationAnalytics(filters) {
    const locationsList = document.getElementById('top-user-locations-list');
    const locationButtons = Array.from(document.querySelectorAll('.location-toggle-btn'));
    const requestConfig = window.wpikoChatbotAnalytics || {};

    if (!locationsList || locationButtons.length === 0) {
        return null;
    }

    const locationsCard = locationsList.closest('.analytics-card');
    const locationViewField = filters.analyticsForm
        ? filters.analyticsForm.querySelector('input[name="location_view"]')
        : null;
    const initialView = window.analyticsData && window.analyticsData.currentLocationView
        ? window.analyticsData.currentLocationView
        : 'country';
    const initialLocations = window.analyticsData && Array.isArray(window.analyticsData.locationDistribution)
        ? window.analyticsData.locationDistribution
        : [];
    const locationCache = new Map([[initialView, initialLocations]]);
    const numberFormatter = new Intl.NumberFormat();
    let currentView = initialView;
    let activeRequest = 0;

    function renderLocations(locations, view) {
        const fragment = document.createDocumentFragment();

        if (locations.length === 0) {
            const emptyState = document.createElement('div');
            const emptyMessage = document.createElement('p');

            emptyState.className = 'no-data-message';
            emptyMessage.textContent = 'No ' + view + ' data available for the selected period';
            emptyState.appendChild(emptyMessage);
            fragment.appendChild(emptyState);
            locationsList.replaceChildren(fragment);
            return;
        }

        const maximumCount = locations.reduce(function(maximum, location) {
            const count = Number(location.count);
            return Number.isFinite(count) ? Math.max(maximum, count) : maximum;
        }, 0);

        locations.forEach(function(location) {
            const locationName = location.location ? String(location.location) : 'Unknown';
            const count = Number(location.count);
            const safeCount = Number.isFinite(count) ? count : 0;
            const percentage = maximumCount > 0 ? (safeCount / maximumCount) * 100 : 0;
            const item = document.createElement('div');
            const name = document.createElement('span');
            const barContainer = document.createElement('div');
            const bar = document.createElement('div');
            const countElement = document.createElement('span');

            item.className = 'location-item';
            name.className = 'location-name';
            name.title = locationName;
            name.textContent = locationName;
            barContainer.className = 'activity-bar-container';
            bar.className = 'activity-bar';
            bar.style.width = percentage + '%';
            countElement.className = 'count';
            countElement.textContent = numberFormatter.format(safeCount);

            barContainer.appendChild(bar);
            item.append(name, barContainer, countElement);
            fragment.appendChild(item);
        });

        locationsList.replaceChildren(fragment);
    }

    function renderError(message) {
        const errorState = document.createElement('div');
        const icon = document.createElement('span');
        const errorMessage = document.createElement('p');

        errorState.className = 'analytics-error';
        icon.className = 'dashicons dashicons-warning';
        icon.setAttribute('aria-hidden', 'true');
        errorMessage.textContent = message;
        errorState.append(icon, errorMessage);
        locationsList.replaceChildren(errorState);
    }

    function updateViewState(view) {
        currentView = view;

        locationButtons.forEach(function(button) {
            const isActive = button.dataset.view === view;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (locationViewField) {
            locationViewField.value = view;
        }

        if (window.analyticsData) {
            window.analyticsData.currentLocationView = view;
            window.analyticsData.locationDistribution = locationCache.get(view) || [];
        }

        const url = new URL(window.location.href);
        url.searchParams.set('location_view', view);
        window.history.replaceState(window.history.state, '', url.toString());
    }

    function setLoading(isLoading) {
        locationsList.setAttribute('aria-busy', isLoading ? 'true' : 'false');

        if (locationsCard) {
            locationsCard.classList.toggle('loading', isLoading);
        }

        locationButtons.forEach(function(button) {
            button.disabled = isLoading;
        });
    }

    function getRequestBody(view) {
        const requestBody = new URLSearchParams();

        requestBody.set('action', requestConfig.locationAction);
        requestBody.set('nonce', requestConfig.locationNonce);
        requestBody.set('location_view', view);
        requestBody.set('date_range', filters.dateRangeSelect && filters.dateRangeSelect.value
            ? filters.dateRangeSelect.value
            : '7');

        if (filters.startDate && filters.startDate.value) {
            requestBody.set('start_date', filters.startDate.value);
        }
        if (filters.endDate && filters.endDate.value) {
            requestBody.set('end_date', filters.endDate.value);
        }

        return requestBody;
    }

    function loadLocationView(view) {
        if (locationCache.has(view)) {
            renderLocations(locationCache.get(view), view);
            updateViewState(view);
            return;
        }

        if (!requestConfig.ajaxUrl || !requestConfig.locationAction || !requestConfig.locationNonce) {
            renderError(requestConfig.locationError || 'Unable to load location analytics. Please try again.');
            return;
        }

        const requestId = ++activeRequest;
        setLoading(true);

        fetch(requestConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: getRequestBody(view).toString()
        })
            .then(function(response) {
                return response.json().then(function(payload) {
                    if (!response.ok || !payload.success || !payload.data || !Array.isArray(payload.data.locations)) {
                        const message = payload.data && payload.data.message
                            ? payload.data.message
                            : requestConfig.locationError;
                        throw new Error(message);
                    }

                    return payload.data;
                });
            })
            .then(function(data) {
                if (requestId !== activeRequest) {
                    return;
                }

                locationCache.set(data.view, data.locations);
                renderLocations(data.locations, data.view);
                updateViewState(data.view);
            })
            .catch(function(error) {
                if (requestId !== activeRequest) {
                    return;
                }

                renderError(error.message || requestConfig.locationError || 'Unable to load location analytics. Please try again.');
            })
            .finally(function() {
                if (requestId === activeRequest) {
                    setLoading(false);
                }
            });
    }

    locationButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            loadLocationView(button.dataset.view);
        });
    });

    updateViewState(currentView);

    return {
        getCurrentView: function() {
            return currentView;
        },
        updateLocations: function(locations, view) {
            const safeLocations = Array.isArray(locations) ? locations : [];

            locationCache.clear();
            locationCache.set(view, safeLocations);
            if (view === currentView) {
                renderLocations(safeLocations, view);
                if (window.analyticsData) {
                    window.analyticsData.locationDistribution = safeLocations;
                }
            }
        }
    };
}

function initializeAnalyticsLiveUpdates(context) {
    const requestConfig = window.wpikoChatbotAnalytics || {};
    const statusRoot = document.querySelector('[data-analytics-live-status]');
    const statusLabel = statusRoot ? statusRoot.querySelector('[data-analytics-live-label]') : null;
    const configuredInterval = Number(requestConfig.liveInterval);
    const refreshInterval = Number.isFinite(configuredInterval)
        ? Math.max(5000, configuredInterval)
        : 15000;
    let refreshTimer = null;
    let requestInProgress = false;
    let stopped = false;
    let lastSnapshotSignature = null;

    if (!requestConfig.ajaxUrl || !requestConfig.liveAction || !requestConfig.liveNonce) {
        if (statusRoot) {
            statusRoot.hidden = true;
        }
        return;
    }

    function setStatus(state, label) {
        if (!statusRoot || !statusLabel) {
            return;
        }

        statusRoot.classList.toggle('is-updating', state === 'updating');
        statusRoot.classList.toggle('has-error', state === 'error');
        statusLabel.textContent = label;
    }

    function scheduleNextRefresh(delay) {
        if (stopped) {
            return;
        }

        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(refresh, delay);
    }

    function getRequestBody() {
        const requestBody = new URLSearchParams();
        const filters = context.filters || {};
        const locationViewField = filters.analyticsForm
            ? filters.analyticsForm.querySelector('input[name="location_view"]')
            : null;
        const locationView = context.locationAnalytics
            ? context.locationAnalytics.getCurrentView()
            : (locationViewField && locationViewField.value ? locationViewField.value : 'country');

        requestBody.set('action', requestConfig.liveAction);
        requestBody.set('nonce', requestConfig.liveNonce);
        requestBody.set(
            'date_range',
            filters.dateRangeSelect && filters.dateRangeSelect.value
                ? filters.dateRangeSelect.value
                : '7'
        );
        requestBody.set('location_view', locationView);

        if (filters.startDate && filters.startDate.value) {
            requestBody.set('start_date', filters.startDate.value);
        }
        if (filters.endDate && filters.endDate.value) {
            requestBody.set('end_date', filters.endDate.value);
        }

        return requestBody;
    }

    function refresh() {
        if (stopped || requestInProgress) {
            return;
        }

        if (document.hidden) {
            scheduleNextRefresh(refreshInterval);
            return;
        }

        requestInProgress = true;
        setStatus('updating', requestConfig.liveUpdatingLabel || 'Updating…');

        fetch(requestConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: getRequestBody().toString()
        })
            .then(function(response) {
                return response.json().then(function(payload) {
                    if (!response.ok || !payload.success || !payload.data || !payload.data.snapshot) {
                        const message = payload.data && payload.data.message
                            ? payload.data.message
                            : requestConfig.liveErrorLabel;
                        throw new Error(message);
                    }

                    return payload.data;
                });
            })
            .then(function(data) {
                const snapshotSignature = JSON.stringify(data.snapshot);

                if (snapshotSignature !== lastSnapshotSignature) {
                    updateAnalyticsDashboard(data.snapshot, {
                        locationView: data.location_view,
                        locationAnalytics: context.locationAnalytics,
                        messageActivityChart: context.messageActivityChart
                    });
                    lastSnapshotSignature = snapshotSignature;
                }
                setStatus('live', requestConfig.liveLabel || 'Live');
            })
            .catch(function() {
                setStatus('error', requestConfig.liveErrorLabel || 'Reconnecting…');
            })
            .finally(function() {
                requestInProgress = false;
                scheduleNextRefresh(refreshInterval);
            });
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            window.clearTimeout(refreshTimer);
            return;
        }

        scheduleNextRefresh(0);
    });

    window.addEventListener('pagehide', function() {
        stopped = true;
        window.clearTimeout(refreshTimer);
    });

    scheduleNextRefresh(refreshInterval);
}

function updateAnalyticsDashboard(snapshot, controllers) {
    const numberFormatter = new Intl.NumberFormat();
    const hasData = Boolean(snapshot.has_data);
    const emptyState = document.querySelector('[data-analytics-empty-state]');
    const summaryGrid = document.querySelector('[data-analytics-summary]');
    const totalConversations = toAnalyticsNumber(snapshot.total_conversations);
    const totalMessages = toAnalyticsNumber(snapshot.total_messages);
    const totalUsers = toAnalyticsNumber(snapshot.total_users);
    const conversationChange = toAnalyticsNumber(snapshot.conversation_change);
    const averageMessages = toAnalyticsNumber(snapshot.avg_messages);
    const errorCount = toAnalyticsNumber(snapshot.error_count);
    const errorRate = toAnalyticsNumber(snapshot.error_rate);

    if (emptyState) {
        emptyState.hidden = hasData;
    }
    if (summaryGrid) {
        summaryGrid.hidden = !hasData;
    }

    setAnalyticsText('[data-analytics-total-conversations]', numberFormatter.format(totalConversations));
    setAnalyticsText('[data-analytics-total-messages]', numberFormatter.format(totalMessages));
    setAnalyticsText('[data-analytics-total-users]', numberFormatter.format(totalUsers));
    setAnalyticsText('[data-analytics-conversation-change]', numberFormatter.format(Math.abs(conversationChange)));
    setAnalyticsText('[data-analytics-average-messages]', averageMessages.toFixed(1));
    setAnalyticsText('[data-analytics-error-count]', numberFormatter.format(errorCount));
    setAnalyticsText('[data-analytics-error-rate]', errorRate.toFixed(1) + '%');

    const trend = document.querySelector('[data-analytics-conversation-trend]');
    if (trend) {
        const isPositive = conversationChange >= 0;
        const trendIcon = trend.querySelector('.dashicons');

        trend.classList.toggle('positive', isPositive);
        trend.classList.toggle('negative', !isPositive);
        if (trendIcon) {
            trendIcon.classList.toggle('dashicons-arrow-up-alt', isPositive);
            trendIcon.classList.toggle('dashicons-arrow-down-alt', !isPositive);
        }
    }

    const errorRateElement = document.querySelector('[data-analytics-error-rate]');
    if (errorRateElement) {
        errorRateElement.classList.toggle('warning', errorRate > 3);
    }

    if (controllers.messageActivityChart) {
        controllers.messageActivityChart.updateData(snapshot.daily_messages);
    }
    if (window.analyticsData) {
        window.analyticsData.dailyMessages = Array.isArray(snapshot.daily_messages)
            ? snapshot.daily_messages
            : [];
    }

    if (controllers.locationAnalytics) {
        controllers.locationAnalytics.updateLocations(
            snapshot.locations,
            controllers.locationView || controllers.locationAnalytics.getCurrentView()
        );
    }

    renderAnalyticsBarList(
        document.querySelector('.conversation-length-list'),
        snapshot.conversation_length_bins,
        'conversation-length-item',
        'range',
        function(label) {
            return label;
        }
    );
    renderAnalyticsBarList(
        document.querySelector('.peak-hours-list'),
        snapshot.busy_hours,
        'peak-hour-item',
        'hour',
        function(hour) {
            return formatAnalyticsHour(hour);
        }
    );
    updateAnalyticsDeviceStats(snapshot, numberFormatter);
}

function renderAnalyticsBarList(container, dataset, itemClass, labelClass, labelFormatter) {
    if (!container) {
        return;
    }

    const items = [];
    if (Array.isArray(dataset)) {
        dataset.forEach(function(item) {
            items.push({
                label: item.hour,
                count: toAnalyticsNumber(item.count)
            });
        });
    } else if (dataset && typeof dataset === 'object') {
        Object.keys(dataset).forEach(function(label) {
            items.push({
                label: label,
                count: toAnalyticsNumber(dataset[label])
            });
        });
    }

    const maximumCount = items.reduce(function(maximum, item) {
        return Math.max(maximum, item.count);
    }, 0);
    const numberFormatter = new Intl.NumberFormat();
    const fragment = document.createDocumentFragment();

    items.forEach(function(item) {
        const row = document.createElement('div');
        const label = document.createElement('span');
        const barContainer = document.createElement('div');
        const bar = document.createElement('div');
        const count = document.createElement('span');

        row.className = itemClass;
        label.className = labelClass;
        label.textContent = labelFormatter(item.label);
        barContainer.className = 'activity-bar-container';
        bar.className = 'activity-bar';
        bar.style.width = (maximumCount > 0 ? (item.count / maximumCount) * 100 : 0) + '%';
        count.className = 'count';
        count.textContent = numberFormatter.format(item.count);
        barContainer.appendChild(bar);
        row.append(label, barContainer, count);
        fragment.appendChild(row);
    });

    container.replaceChildren(fragment);
}

function updateAnalyticsDeviceStats(snapshot, numberFormatter) {
    const totalUserMessages = toAnalyticsNumber(snapshot.total_user_messages);
    const deviceCounts = snapshot.device_counts || {};
    const devicePercentages = snapshot.device_percentages || {};

    setAnalyticsText('[data-analytics-device-total]', numberFormatter.format(totalUserMessages));

    ['desktop', 'mobile', 'tablet'].forEach(function(deviceType) {
        const deviceBox = document.querySelector('[data-analytics-device="' + deviceType + '"]');

        if (!deviceBox) {
            return;
        }

        const percentage = toAnalyticsNumber(devicePercentages[deviceType]);
        const count = toAnalyticsNumber(deviceCounts[deviceType]);
        const percentageElement = deviceBox.querySelector('.device-percentage');
        const countElement = deviceBox.querySelector('.device-count');

        if (percentageElement) {
            percentageElement.textContent = Math.round(percentage) + '%';
        }
        if (countElement) {
            countElement.textContent = numberFormatter.format(count);
        }
    });
}

function setAnalyticsText(selector, value) {
    const element = document.querySelector(selector);

    if (element) {
        element.textContent = value;
    }
}

function toAnalyticsNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
}

function formatAnalyticsHour(hour) {
    const normalizedHour = ((toAnalyticsNumber(hour) % 24) + 24) % 24;
    const displayHour = normalizedHour % 12 || 12;
    return displayHour + (normalizedHour < 12 ? 'am' : 'pm');
}

function initializeMessageActivityChart(chartRoot, dailyMessages) {
    const seriesConfig = {
        total: {
            key: 'total_count',
            label: 'Total messages',
            singular: 'message',
            plural: 'messages',
            color: '#0968FE',
            emptyTitle: 'No messages recorded'
        },
        user: {
            key: 'user_count',
            label: 'User messages',
            singular: 'user message',
            plural: 'user messages',
            color: '#0968FE',
            emptyTitle: 'No user messages recorded'
        },
        assistant: {
            key: 'assistant_count',
            label: 'Assistant messages',
            singular: 'assistant message',
            plural: 'assistant messages',
            color: '#7C3AED',
            emptyTitle: 'No assistant messages recorded'
        },
        error: {
            key: 'error_count',
            label: 'Errors',
            singular: 'error',
            plural: 'errors',
            color: '#DC2626',
            emptyTitle: 'No errors recorded'
        }
    };

    const seriesButtons = document.querySelectorAll('.message-series-btn');
    const summary = document.getElementById('message-activity-summary');
    const yAxis = chartRoot.querySelector('.y-axis-labels');
    const chartArea = chartRoot.querySelector('.message-chart-area');
    const gridlines = chartRoot.querySelector('.chart-gridlines');
    const chartFill = chartRoot.querySelector('.message-chart-fill');
    const chartLine = chartRoot.querySelector('.message-chart-line');
    const gradientStart = chartRoot.querySelector('.message-chart-gradient-start');
    const chartPoints = chartRoot.querySelector('.chart-points');
    const dateLabels = chartRoot.querySelector('.date-labels');
    const verticalLine = chartRoot.querySelector('.vertical-line');
    const tooltip = chartRoot.querySelector('.chart-tooltip');
    const emptyState = chartRoot.querySelector('.chart-empty-state');
    const emptyTitle = chartRoot.querySelector('.chart-empty-title');
    const dataTable = chartRoot.querySelector('.message-activity-data-table');
    const tableCaption = dataTable.querySelector('caption');
    const tableValueHeading = dataTable.querySelector('thead th:last-child');
    const tableBody = dataTable.querySelector('tbody');
    const numberFormatter = new Intl.NumberFormat();

    let selectedSeries = 'total';
    let currentScale = getChartScale(0);
    let resizeFrame = null;

    function getXPosition(index) {
        if (dailyMessages.length <= 1) {
            return 50;
        }

        return (index / (dailyMessages.length - 1)) * 100;
    }

    function getValues(config) {
        return dailyMessages.map(function(day) {
            const value = Number(day[config.key]);
            return Number.isFinite(value) ? value : 0;
        });
    }

    function renderGridAndLabels() {
        yAxis.replaceChildren();
        gridlines.replaceChildren();
        dateLabels.replaceChildren();

        currentScale.ticks.forEach(function(tick) {
            const top = (1 - (tick / currentScale.max)) * 100;
            const yLabel = document.createElement('div');
            const horizontalLine = document.createElement('span');

            yLabel.className = 'y-label';
            yLabel.style.top = top + '%';
            yLabel.textContent = numberFormatter.format(tick);
            yAxis.appendChild(yLabel);

            horizontalLine.className = 'chart-gridline chart-y-gridline';
            horizontalLine.style.top = top + '%';
            gridlines.appendChild(horizontalLine);
        });

        getDateLabelIndices(dailyMessages.length, chartArea.clientWidth).forEach(function(index) {
            const left = getXPosition(index);
            const day = dailyMessages[index];
            const dateLabel = document.createElement('div');
            const verticalGridline = document.createElement('span');

            dateLabel.className = 'date-label';
            dateLabel.style.left = left + '%';
            dateLabel.textContent = formatChartDate(day.date, dailyMessages.length > 180);

            if (index === 0) {
                dateLabel.classList.add('is-first');
            }
            if (index === dailyMessages.length - 1) {
                dateLabel.classList.add('is-last');
            }

            dateLabels.appendChild(dateLabel);

            verticalGridline.className = 'chart-gridline chart-x-gridline';
            verticalGridline.style.left = left + '%';
            gridlines.appendChild(verticalGridline);
        });
    }

    function hideTooltip() {
        tooltip.hidden = true;
        verticalLine.hidden = true;
        chartPoints.querySelectorAll('.chart-dot[aria-describedby]').forEach(function(dot) {
            dot.removeAttribute('aria-describedby');
        });
    }

    function showTooltip(dot) {
        const value = Number(dot.dataset.value);
        const config = seriesConfig[selectedSeries];
        const noun = value === 1 ? config.singular : config.plural;
        const dotLeft = dot.offsetLeft;
        const dotTop = dot.offsetTop;

        tooltip.textContent = dot.dataset.date + ': ' + numberFormatter.format(value) + ' ' + noun;
        tooltip.hidden = false;
        verticalLine.hidden = false;
        verticalLine.style.left = dotLeft + 'px';

        const halfTooltipWidth = tooltip.offsetWidth / 2;
        const minimumLeft = halfTooltipWidth + 6;
        const maximumLeft = chartArea.clientWidth - halfTooltipWidth - 6;
        const tooltipLeft = Math.min(Math.max(dotLeft, minimumLeft), Math.max(minimumLeft, maximumLeft));
        let tooltipTop = dotTop - tooltip.offsetHeight - 12;

        if (tooltipTop < 4) {
            tooltipTop = dotTop + 14;
        }

        tooltip.style.left = tooltipLeft + 'px';
        tooltip.style.top = Math.min(tooltipTop, chartArea.clientHeight - tooltip.offsetHeight - 4) + 'px';
        dot.setAttribute('aria-describedby', tooltip.id);
    }

    function addPointInteractions(dot) {
        dot.addEventListener('mouseenter', function() {
            showTooltip(dot);
        });

        dot.addEventListener('mouseleave', function() {
            if (document.activeElement !== dot) {
                hideTooltip();
            }
        });

        dot.addEventListener('focus', function() {
            showTooltip(dot);
        });

        dot.addEventListener('blur', function() {
            hideTooltip();
        });

        dot.addEventListener('click', function(event) {
            event.stopPropagation();
            showTooltip(dot);
        });

        dot.addEventListener('keydown', function(event) {
            const dots = Array.from(chartPoints.querySelectorAll('.chart-dot'));
            const currentIndex = dots.indexOf(dot);
            let nextIndex = currentIndex;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = Math.min(currentIndex + 1, dots.length - 1);
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = Math.max(currentIndex - 1, 0);
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = dots.length - 1;
            } else if (event.key === 'Escape') {
                hideTooltip();
                return;
            } else {
                return;
            }

            event.preventDefault();
            dots.forEach(function(point, index) {
                point.tabIndex = index === nextIndex ? 0 : -1;
            });
            dots[nextIndex].focus();
        });
    }

    function renderPoints(values, config) {
        chartPoints.replaceChildren();

        values.forEach(function(value, index) {
            const x = getXPosition(index);
            const y = (1 - (value / currentScale.max)) * 100;
            const date = formatChartDate(dailyMessages[index].date, false);
            const noun = value === 1 ? config.singular : config.plural;
            const dot = document.createElement('button');

            dot.type = 'button';
            dot.className = 'chart-dot' + (value === 0 ? ' zero-value' : '');
            dot.style.left = x + '%';
            dot.style.top = y + '%';
            dot.dataset.value = value;
            dot.dataset.date = date;
            dot.dataset.index = index;
            dot.tabIndex = index === 0 ? 0 : -1;
            dot.setAttribute('aria-label', date + ': ' + numberFormatter.format(value) + ' ' + noun);

            addPointInteractions(dot);
            chartPoints.appendChild(dot);
        });
    }

    function renderTable(values, config) {
        tableCaption.textContent = config.label + ' by date';
        tableValueHeading.textContent = config.label;
        tableBody.replaceChildren();

        values.forEach(function(value, index) {
            const row = document.createElement('tr');
            const dateCell = document.createElement('th');
            const valueCell = document.createElement('td');

            dateCell.scope = 'row';
            dateCell.textContent = formatChartDate(dailyMessages[index].date, true);
            valueCell.textContent = numberFormatter.format(value);
            row.append(dateCell, valueCell);
            tableBody.appendChild(row);
        });
    }

    function renderSummary(values, config) {
        const total = values.reduce(function(sum, value) {
            return sum + value;
        }, 0);
        const period = dailyMessages.length
            ? formatChartDate(dailyMessages[0].date, true) + ' – ' + formatChartDate(dailyMessages[dailyMessages.length - 1].date, true)
            : 'Selected period';

        if (total === 0) {
            summary.textContent = config.emptyTitle + ' · ' + period;
            return;
        }

        const peakValue = Math.max.apply(null, values);
        const peakIndex = values.indexOf(peakValue);
        const totalNoun = total === 1 ? config.singular : config.plural;
        const peakLabel = selectedSeries === 'error' ? 'Highest day' : 'Busiest day';

        summary.textContent =
            numberFormatter.format(total) + ' ' + totalNoun +
            ' · ' + peakLabel + ' ' + formatChartDate(dailyMessages[peakIndex].date, false) +
            ' (' + numberFormatter.format(peakValue) + ')' +
            ' · ' + period;
    }

    function renderChart(series) {
        const config = seriesConfig[series];
        const values = getValues(config);
        const maxValue = values.length ? Math.max.apply(null, values) : 0;
        const isEmpty = values.length === 0 || values.every(function(value) {
            return value === 0;
        });

        selectedSeries = series;
        currentScale = getChartScale(maxValue);
        chartRoot.dataset.series = series;
        chartRoot.style.setProperty('--message-series-color', config.color);
        gradientStart.setAttribute('stop-color', config.color);

        seriesButtons.forEach(function(button) {
            const isActive = button.dataset.series === series;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        renderGridAndLabels();
        renderSummary(values, config);
        renderTable(values, config);
        hideTooltip();

        emptyState.hidden = !isEmpty;
        emptyTitle.textContent = config.emptyTitle;
        chartArea.classList.toggle('is-empty', isEmpty);

        if (isEmpty) {
            chartFill.setAttribute('points', '');
            chartLine.setAttribute('points', '');
            chartPoints.replaceChildren();
            return;
        }

        const linePoints = values.map(function(value, index) {
            const x = getXPosition(index);
            const y = (1 - (value / currentScale.max)) * 100;
            return x + ',' + y;
        }).join(' ');
        const firstX = getXPosition(0);
        const lastX = getXPosition(values.length - 1);

        chartLine.setAttribute('points', linePoints);
        chartFill.setAttribute('points', firstX + ',100 ' + linePoints + ' ' + lastX + ',100');
        renderPoints(values, config);
    }

    seriesButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            renderChart(button.dataset.series);
        });
    });

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.chart-dot')) {
            hideTooltip();
        }
    });

    if ('ResizeObserver' in window) {
        const resizeObserver = new ResizeObserver(function() {
            if (resizeFrame) {
                window.cancelAnimationFrame(resizeFrame);
            }

            resizeFrame = window.requestAnimationFrame(function() {
                renderGridAndLabels();
                resizeFrame = null;
            });
        });

        resizeObserver.observe(chartArea);
    }

    renderChart(selectedSeries);

    return {
        updateData: function(updatedDailyMessages) {
            dailyMessages = Array.isArray(updatedDailyMessages) ? updatedDailyMessages : [];
            renderChart(selectedSeries);
        }
    };
}

function getChartScale(maxValue) {
    if (maxValue <= 0) {
        return {
            max: 4,
            ticks: [4, 3, 2, 1, 0]
        };
    }

    const rawStep = maxValue / 4;
    const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)));
    const normalizedStep = rawStep / magnitude;
    let niceNormalizedStep = 1;

    if (normalizedStep > 5) {
        niceNormalizedStep = 10;
    } else if (normalizedStep > 2) {
        niceNormalizedStep = 5;
    } else if (normalizedStep > 1) {
        niceNormalizedStep = 2;
    }

    const step = Math.max(1, niceNormalizedStep * magnitude);
    const max = Math.ceil(maxValue / step) * step;
    const ticks = [];

    for (let tick = max; tick >= 0; tick -= step) {
        ticks.push(tick);
    }

    if (ticks[ticks.length - 1] !== 0) {
        ticks.push(0);
    }

    return { max: max, ticks: ticks };
}

function getDateLabelIndices(pointCount, chartWidth) {
    if (pointCount <= 0) {
        return [];
    }

    const maximumLabels = chartWidth < 420 ? 4 : (chartWidth < 650 ? 6 : 9);
    const labelCount = Math.min(pointCount, maximumLabels);

    if (labelCount === 1) {
        return [0];
    }

    const indices = [];
    for (let index = 0; index < labelCount; index++) {
        const pointIndex = Math.round((index * (pointCount - 1)) / (labelCount - 1));
        if (!indices.includes(pointIndex)) {
            indices.push(pointIndex);
        }
    }

    return indices;
}

function formatChartDate(dateString, includeYear) {
    const dateParts = String(dateString).split('-').map(Number);
    const date = new Date(Date.UTC(dateParts[0], dateParts[1] - 1, dateParts[2]));
    const options = {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC'
    };

    if (includeYear) {
        options.year = 'numeric';
    }

    try {
        return new Intl.DateTimeFormat(document.documentElement.lang || undefined, options).format(date);
    } catch (error) {
        return dateString;
    }
}
