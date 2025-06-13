// Dashboard JavaScript
// File: assets/js/dashboard.js

jQuery(document).ready(function($) {
    'use strict';
    
    const StravaCoachDashboard = {
        init: function() {
            this.bindEvents();
            this.loadInitialData();
            this.initCharts();
        createActivityTypeChart: function(canvasId) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                viewMenteeProgress: function(e) {
            e.preventDefault();
            
            const menteeId = $(this).data('mentee-id');
            const menteeName = $(this).closest('.mentee-card').find('h3').text();
            
            // Set mentee filter to show this mentee's data
            $('#mentee-filter').val(menteeId).trigger('change');
            
            // Scroll to analytics section
            $('html, body').animate({
                scrollTop: $('.analytics-controls').offset().top - 100
            }, 800);
            
            // Show a notification
            const notification = $('<div class="progress-notification">Showing progress for ' + menteeName + '</div>');
            $('body').append(notification);
            notification.fadeIn(300).delay(2000).fadeOut(300, function() {
                $(this).remove();
            });
        },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Activity Types Distribution'
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed * 100) / total).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        createWeeklyProgressChart: function(canvasId, chartType = 'bar') {
            const ctx = document.getElementById(canvasId).getContext('2d');
            
            const baseType = chartType === 'line' ? 'line' : 'bar';
            const secondaryType = chartType === 'mixed' ? 'line' : baseType;
            
            return new Chart(ctx, {
                type: baseType,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Distance (km)',
                        data: [],
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        type: baseType
                    }, {
                        label: 'Activities',
                        data: [],
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgb(255, 99, 132)',
                        borderWidth: 1,
                        yAxisID: 'y1',
                        type: secondaryType
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Distance (km)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Number of Activities'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Weekly Progress (' + chartType.charAt(0).toUpperCase() + chartType.slice(1) + ')'
                        },
                        legend: {
                            display: true
                        }
                    }
                }
            });
        },
        
        createPlanComparisonChart: function(canvasId) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Planned Distance (km)',
                        data: [],
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.1
                    }, {
                        label: 'Actual Distance (km)',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderWidth: 2,
                        tension: 0.1
                    }, {
                        label: 'Planned Activities',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.1,
                        yAxisID: 'y1'
                    }, {
                        label: 'Actual Activities',
                        data: [],
                        borderColor: 'rgb(255, 205, 86)',
                        backgroundColor: 'rgba(255, 205, 86, 0.1)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Distance (km)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Number of Activities'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Plan vs Actual Performance'
                        },
                        legend: {
                            display: true
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            });
        },
        
        bindEvents: function() {
            // Modal controls
            $('.modal .close').on('click', this.closeModal);
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('modal')) {
                    StravaCoachDashboard.closeModal();
                }
            });
            
            // Coach Dashboard Events
            if ($('#coach-dashboard').length) {
                this.bindCoachEvents();
            }
            
            // Mentee Dashboard Events
            if ($('#mentee-dashboard').length) {
                this.bindMenteeEvents();
            }
            
            // Common Events
            $('#refresh-analytics').on('click', this.refreshAnalytics);
            $('#time-filter, #activity-filter, #mentee-filter, #chart-type-filter').on('change', this.refreshAnalytics);
        },
        
        bindCoachEvents: function() {
            // Mentee management
            $('#add-mentee-btn, #add-first-mentee').on('click', this.showAddMenteeModal);
            $('#add-mentee-form').on('submit', this.addMentee);
            $('.remove-mentee').on('click', this.removeMentee);
            $('.view-mentee-progress').on('click', this.viewMenteeProgress);
            $('.create-weekly-plan').on('click', this.showWeeklyPlanModal);
            
            // Weekly planning
            $('#weekly-plan-form').on('submit', this.saveWeeklyPlan);
            
            // Scoring
            $('.score-weekly-plan').on('click', this.showScoringModal);
            $('#scoring-form').on('submit', this.submitScore);
            $('#scoring-form input[type="range"]').on('input', this.updateScoreDisplay);
        },
        
        bindMenteeEvents: function() {
            // Strava connection
            $('#connect-strava').on('click', this.connectStrava);
            $('#sync-strava').on('click', this.syncStrava);
            $('#disconnect-strava').on('click', this.disconnectStrava);
        },
        
        loadInitialData: function() {
            this.checkStravaConnection();
            this.loadDashboardStats();
            this.loadCurrentPlans();
            this.loadWeeklyScores();
            this.loadMenteeStats(); // Add this
        },
        
        loadMenteeStats: function() {
            // Load stats for each mentee card
            $('.mentee-card').each(function() {
                const menteeId = $(this).data('mentee-id');
                const $card = $(this);
                
                $.ajax({
                    url: strava_coach_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_mentee_stats',
                        mentee_id: menteeId,
                        nonce: strava_coach_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const stats = response.data;
                            $card.find('.activity-count').text(stats.activity_count + ' activities');
                            $card.find('.last-activity').text('Last activity: ' + (stats.last_activity || 'None'));
                        }
                    },
                    error: function() {
                        $card.find('.activity-count').text('Error loading');
                        $card.find('.last-activity').text('Error loading');
                    }
                });
            });
        },
        
        initCharts: function() {
            if ($('#performance-chart').length) {
                this.performanceChart = this.createPerformanceChart('performance-chart');
            }
            if ($('#mentee-performance-chart').length) {
                this.menteePerformanceChart = this.createPerformanceChart('mentee-performance-chart');
            }
            this.refreshAnalytics();
        },
        
        createPerformanceChart: function(canvasId, chartType = 'line') {
            const ctx = document.getElementById(canvasId).getContext('2d');
            
            return new Chart(ctx, {
                type: chartType,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Distance (km)',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: chartType === 'line' ? 0.1 : 0,
                        yAxisID: 'y'
                    }, {
                        label: 'Duration (min)',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: chartType === 'line' ? 0.1 : 0,
                        yAxisID: 'y1'
                    }, {
                        label: 'Avg Pace (min/km)',
                        data: [],
                        borderColor: 'rgb(255, 205, 86)',
                        backgroundColor: 'rgba(255, 205, 86, 0.2)',
                        tension: chartType === 'line' ? 0.1 : 0,
                        yAxisID: 'y2'
                    }, {
                        label: 'Elevation (m)',
                        data: [],
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: chartType === 'line' ? 0.1 : 0,
                        yAxisID: 'y3'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Distance (km)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: false,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                        y2: {
                            type: 'linear',
                            display: false,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                        y3: {
                            type: 'linear',
                            display: false,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Performance Analytics'
                        },
                        legend: {
                            display: true,
                            position: 'top',
                            onClick: function(e, legendItem, legend) {
                                const index = legendItem.datasetIndex;
                                const chart = legend.chart;
                                const meta = chart.getDatasetMeta(index);
                                
                                // Toggle visibility
                                meta.hidden = !meta.hidden;
                                
                                // Show/hide corresponding y-axis
                                const yAxisId = chart.data.datasets[index].yAxisID;
                                if (yAxisId && chart.options.scales[yAxisId]) {
                                    chart.options.scales[yAxisId].display = !meta.hidden;
                                }
                                
                                chart.update();
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return 'Date: ' + context[0].label;
                                },
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    
                                    if (context.dataset.label.includes('Pace')) {
                                        label += formatPace(context.parsed.y);
                                    } else if (context.dataset.label.includes('Distance')) {
                                        label += context.parsed.y + ' km';
                                    } else if (context.dataset.label.includes('Duration')) {
                                        label += formatDuration(context.parsed.y * 60);
                                    } else if (context.dataset.label.includes('Elevation')) {
                                        label += context.parsed.y + ' m';
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        refreshAnalytics: function() {
            const filters = {
                time_period: $('#time-filter').val() || '30',
                activity_type: $('#activity-filter').val() || '',
                mentee_id: $('#mentee-filter').val() || '',
                chart_type: $('#chart-type-filter').val() || 'line'
            };
            
            StravaCoachDashboard.loadAnalyticsData(filters);
        },
        
        loadAnalyticsData: function(filters) {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_analytics_data',
                    nonce: strava_coach_ajax.nonce,
                    filters: filters
                },
                success: function(response) {
                    if (response.success) {
                        StravaCoachDashboard.updateCharts(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load analytics data');
                }
            });
        },
        
        updateCharts: function(data) {
            const chartType = $('#chart-type-filter').val() || 'line';
            
            // Recreate chart if type changed
            if (this.currentChartType !== chartType) {
                if (this.performanceChart) {
                    this.performanceChart.destroy();
                    this.performanceChart = this.createPerformanceChart('performance-chart', chartType);
                }
                if (this.menteePerformanceChart) {
                    this.menteePerformanceChart.destroy();
                    this.menteePerformanceChart = this.createPerformanceChart('mentee-performance-chart', chartType);
                }
                this.currentChartType = chartType;
            }
            
            const chart = this.performanceChart || this.menteePerformanceChart;
            if (!chart) return;
            
            // Update chart data
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.distances;
            chart.data.datasets[1].data = data.durations;
            chart.data.datasets[2].data = data.paces;
            chart.data.datasets[3].data = data.elevations;
            chart.update('none');
            
            // Update summary statistics
            this.updateSummaryStats(data.summary || {});
        },
        
        updateSummaryStats: function(summary) {
            const prefix = $('#performance-chart').length ? 'mentee-' : '';
            
            if (summary.total_distance !== undefined) {
                $('#' + prefix + 'total-distance').text(summary.total_distance.toFixed(1) + ' km');
            }
            if (summary.total_activities !== undefined) {
                $('#' + prefix + 'total-activities').text(summary.total_activities);
            }
            if (summary.avg_pace && summary.avg_pace > 0) {
                $('#' + prefix + 'avg-pace').text(formatPace(summary.avg_pace));
            }
            if (summary.total_elevation !== undefined) {
                $('#' + prefix + 'total-elevation').text(summary.total_elevation.toFixed(0) + ' m');
            }
        },
        
        checkStravaConnection: function() {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_strava_connection',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        StravaCoachDashboard.updateStravaStatus(response.data);
                    }
                }
            });
        },
        
        updateStravaStatus: function(data) {
            const $status = $('#strava-status');
            const $connectBtn = $('#connect-strava');
            const $syncBtn = $('#sync-strava');
            const $disconnectBtn = $('#disconnect-strava');
            const $lastSync = $('#last-sync');
            
            if (data.connected) {
                $status.html('✅ Connected to Strava as ' + data.athlete_name)
                       .removeClass('strava-disconnected')
                       .addClass('strava-connected');
                $connectBtn.hide();
                $syncBtn.show();
                $disconnectBtn.show();
                
                if (data.last_sync) {
                    $lastSync.html('Last sync: ' + data.last_sync);
                }
            } else {
                $status.html('❌ Not connected to Strava')
                       .removeClass('strava-connected')
                       .addClass('strava-disconnected');
                $connectBtn.show();
                $syncBtn.hide();
                $disconnectBtn.hide();
                $lastSync.html('');
            }
        },
        
        connectStrava: function(e) {
            e.preventDefault();
            
            // This will redirect to Strava OAuth
            window.location.href = strava_coach_ajax.ajax_url + '?action=strava_oauth_start&nonce=' + strava_coach_ajax.nonce;
        },
        
        syncStrava: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.text('Syncing...').prop('disabled', true);
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sync_strava_data',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Strava data synced successfully!');
                        StravaCoachDashboard.refreshAnalytics();
                        StravaCoachDashboard.checkStravaConnection();
                    } else {
                        alert('Sync failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Sync failed due to network error');
                },
                complete: function() {
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        disconnectStrava: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to disconnect from Strava?')) {
                return;
            }
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'disconnect_strava',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        StravaCoachDashboard.checkStravaConnection();
                        StravaCoachDashboard.refreshAnalytics();
                    }
                }
            });
        },
        
        showAddMenteeModal: function(e) {
            e.preventDefault();
            $('#add-mentee-modal').show();
        },
        
        addMentee: function(e) {
            e.preventDefault();
            
            const menteeId = $('#available-users').val();
            if (!menteeId) {
                alert('Please select a user');
                return;
            }
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'assign_mentee_to_coach',
                    coach_id: strava_coach_ajax.current_user_id,
                    mentee_id: menteeId,
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        },
        
        removeMentee: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to remove this mentee?')) {
                return;
            }
            
            const menteeId = $(this).data('mentee-id');
            const $card = $(this).closest('.mentee-card');
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_mentee_from_coach',
                    coach_id: strava_coach_ajax.current_user_id,
                    mentee_id: menteeId,
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            // Check if no mentees left
                            if ($('.mentee-card').length === 0) {
                                $('.mentees-grid').html('<p>No mentees assigned yet. <a href="#" id="add-first-mentee">Add your first mentee</a></p>');
                            }
                        });
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to remove mentee'));
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                }
            });
        },
        
        showWeeklyPlanModal: function(e) {
            e.preventDefault();
            
            const menteeId = $(this).data('mentee-id');
            $('#plan-mentee-id').val(menteeId);
            
            // Set default week start to next Monday
            const nextMonday = StravaCoachDashboard.getNextMonday();
            $('#week-start').val(nextMonday);
            
            // Generate daily activity forms
            StravaCoachDashboard.generateDailyActivityForms();
            
            $('#weekly-plan-modal').show();
        },
        
        generateDailyActivityForms: function() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const $container = $('#daily-activities');
            $container.empty();
            
            days.forEach((day, index) => {
                const dayNum = index + 1;
                const dayHtml = `
                    <div class="day-plan">
                        <h4>${day}</h4>
                        <div class="activity-inputs">
                            <div>
                                <label>Activity Type:</label>
                                <select name="activities[${dayNum}][activity_type]">
                                    <option value="">Rest Day</option>
                                    <option value="Run">Running</option>
                                    <option value="Ride">Cycling</option>
                                    <option value="Swim">Swimming</option>
                                    <option value="Walk">Walking</option>
                                    <option value="Workout">Strength Training</option>
                                </select>
                            </div>
                            <div>
                                <label>Activity Name:</label>
                                <input type="text" name="activities[${dayNum}][activity_name]" placeholder="e.g., Easy Run" />
                            </div>
                            <div>
                                <label>Distance (km):</label>
                                <input type="number" name="activities[${dayNum}][target_distance]" step="0.1" min="0" />
                            </div>
                            <div>
                                <label>Duration (minutes):</label>
                                <input type="number" name="activities[${dayNum}][target_duration]" min="0" />
                            </div>
                            <div>
                                <label>Target Pace (min/km):</label>
                                <input type="number" name="activities[${dayNum}][target_pace]" step="0.01" min="0" />
                            </div>
                            <div>
                                <label>Target Elevation (m):</label>
                                <input type="number" name="activities[${dayNum}][target_elevation]" step="1" min="0" />
                            </div>
                            <div>
                                <label>HR Min (bpm):</label>
                                <input type="number" name="activities[${dayNum}][target_heartrate_min]" min="0" max="220" />
                            </div>
                            <div>
                                <label>HR Max (bpm):</label>
                                <input type="number" name="activities[${dayNum}][target_heartrate_max]" min="0" max="220" />
                            </div>
                            <div>
                                <label>Intensity:</label>
                                <select name="activities[${dayNum}][intensity_level]">
                                    <option value="recovery">Recovery</option>
                                    <option value="easy">Easy</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="hard">Hard</option>
                                    <option value="very_hard">Very Hard</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Notes:</label>
                                <textarea name="activities[${dayNum}][notes]" rows="2" placeholder="Additional instructions..."></textarea>
                            </div>
                        </div>
                        <input type="hidden" name="activities[${dayNum}][day_of_week]" value="${dayNum}" />
                    </div>
                `;
                $container.append(dayHtml);
            });
        },
        
        saveWeeklyPlan: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Saving...');
            
            // Collect form data
            const formData = new FormData();
            formData.append('action', 'save_weekly_plan');
            formData.append('nonce', strava_coach_ajax.nonce);
            formData.append('mentee_id', $('#plan-mentee-id').val());
            formData.append('week_start', $('#week-start').val());
            formData.append('plan_name', $('#plan-name').val());
            
            // Collect activities data
            const activities = {};
            for (let day = 1; day <= 7; day++) {
                const activityType = $(`select[name="activities[${day}][activity_type]"]`).val();
                if (activityType) {
                    activities[day] = {
                        day_of_week: day,
                        activity_type: activityType,
                        activity_name: $(`input[name="activities[${day}][activity_name]"]`).val() || '',
                        target_distance: parseFloat($(`input[name="activities[${day}][target_distance]"]`).val()) || 0,
                        target_duration: parseInt($(`input[name="activities[${day}][target_duration]"]`).val()) || 0,
                        target_pace: parseFloat($(`input[name="activities[${day}][target_pace]"]`).val()) || 0,
                        target_heartrate_min: parseInt($(`input[name="activities[${day}][target_heartrate_min]"]`).val()) || 0,
                        target_heartrate_max: parseInt($(`input[name="activities[${day}][target_heartrate_max]"]`).val()) || 0,
                        target_elevation: parseFloat($(`input[name="activities[${day}][target_elevation]"]`).val()) || 0,
                        intensity_level: $(`select[name="activities[${day}][intensity_level]"]`).val() || 'easy',
                        notes: $(`textarea[name="activities[${day}][notes]"]`).val() || ''
                    };
                }
            }
            
            formData.append('activities', JSON.stringify(activities));
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Weekly plan saved successfully!');
                        StravaCoachDashboard.closeModal();
                        StravaCoachDashboard.loadCurrentPlans();
                        // Reset form
                        $form[0].reset();
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to save plan'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Network error. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        showScoringModal: function(e) {
            e.preventDefault();
            
            const planId = $(this).data('plan-id');
            $('#score-plan-id').val(planId);
            
            // Load current scores if they exist
            StravaCoachDashboard.loadExistingScores(planId);
            
            $('#scoring-modal').show();
        },
        
        loadExistingScores: function(planId) {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_weekly_scores',
                    plan_id: planId,
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const scores = response.data;
                        $('#scoring-form input[name="pace_score"]').val(scores.pace_score || 5);
                        $('#scoring-form input[name="distance_score"]').val(scores.distance_score || 5);
                        $('#scoring-form input[name="consistency_score"]').val(scores.consistency_score || 5);
                        $('#scoring-form input[name="elevation_score"]').val(scores.elevation_score || 5);
                        
                        // Update custom fields
                        $('#scoring-form textarea[name="custom_field_1_value"]').val(scores.custom_field_1_value || '');
                        $('#scoring-form textarea[name="custom_field_2_value"]').val(scores.custom_field_2_value || '');
                        $('#scoring-form textarea[name="custom_field_3_value"]').val(scores.custom_field_3_value || '');
                        $('#scoring-form textarea[name="custom_field_4_value"]').val(scores.custom_field_4_value || '');
                        
                        $('#scoring-form textarea[name="coach_notes"]').val(scores.coach_notes || '');
                        
                        // Trigger display updates
                        $('#scoring-form input[type="range"]').trigger('input');
                    }
                }
            });
        },
        
        updateScoreDisplay: function() {
            const value = $(this).val();
            $(this).siblings('.score-display').text(value);
        },
        
        submitScore: function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'submit_weekly_score');
            formData.append('nonce', strava_coach_ajax.nonce);
            
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Score submitted successfully!');
                        StravaCoachDashboard.closeModal();
                        StravaCoachDashboard.loadWeeklyScores();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        },
        
        loadDashboardStats: function() {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_dashboard_stats',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('#total-activities').text(stats.total_activities || '-');
                        $('#week-activities').text(stats.week_activities || '-');
                    }
                }
            });
        },
        
        loadCurrentPlans: function() {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_current_plans',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#current-plans-list').html(response.data.current_plans || '<p>No plans for this week.</p>');
                        $('#current-plan').html(response.data.mentee_plan || '<p>No plan assigned for this week.</p>');
                        $('#plan-history').html(response.data.plan_history || '<p>No previous plans.</p>');
                    }
                }
            });
        },
        
        loadWeeklyScores: function() {
            $.ajax({
                url: strava_coach_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_all_weekly_scores',
                    nonce: strava_coach_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#weekly-scores').html(response.data || '<p>No scores available.</p>');
                        $('#pending-scores').html(response.data.pending || '<p>No pending scores.</p>');
                    }
                }
            });
        },
        
        closeModal: function() {
            $('.modal').hide();
        },
        
        getNextMonday: function() {
            const today = new Date();
            const dayOfWeek = today.getDay();
            const daysUntilMonday = dayOfWeek === 0 ? 1 : 8 - dayOfWeek;
            const nextMonday = new Date(today);
            nextMonday.setDate(today.getDate() + daysUntilMonday);
            return nextMonday.toISOString().split('T')[0];
        }
    };
    
    // Initialize dashboard
    StravaCoachDashboard.init();
});

// Utility functions
function formatDistance(meters) {
    return (meters / 1000).toFixed(2) + ' km';
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    if (hours > 0) {
        return hours + 'h ' + minutes + 'm';
    }
    return minutes + 'm';
}

function formatPace(pace) {
    const minutes = Math.floor(pace);
    const seconds = Math.round((pace - minutes) * 60);
    return minutes + ':' + (seconds < 10 ? '0' : '') + seconds + '/km';
}