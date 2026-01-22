document.addEventListener('DOMContentLoaded', function () {
    // --- Slider Logic ---
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    let currentSlide = 0;
    let slideTimer;
    const slideInterval = 5000; // 5 seconds

    function showSlide(index) {
        if (slides.length === 0) return;
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        if (index >= slides.length) currentSlide = 0;
        else if (index < 0) currentSlide = slides.length - 1;
        else currentSlide = index;
        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
        showSlide(currentSlide + 1);
    }

    function prevSlide() {
        showSlide(currentSlide - 1);
    }

    if (nextBtn && prevBtn) {
        nextBtn.addEventListener('click', () => {
            nextSlide();
            resetTimer();
        });

        prevBtn.addEventListener('click', () => {
            prevSlide();
            resetTimer();
        });
    }

    // Dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            showSlide(index);
            resetTimer();
        });
    });

    // Auto Play
    if (slides.length > 0) {
        slideTimer = setInterval(nextSlide, slideInterval);
    }

    function resetTimer() {
        clearInterval(slideTimer);
        slideTimer = setInterval(nextSlide, slideInterval);
    }

    // --- Fetch Real Data ---
    fetch('../api/get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            // 1. Weekly Activity Chart
            const weeklyData = data.weekly_activity || {};
            const ctxActivity = document.getElementById('activityChart').getContext('2d');
            new Chart(ctxActivity, {
                type: 'bar',
                data: {
                    labels: weeklyData.labels || ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'],
                    datasets: [{
                        label: 'ปริมาณขยะที่รีไซเคิล (กก.)',
                        data: weeklyData.data || [0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(46, 204, 113, 0.6)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // 2. Waste Composition Chart
            const ctxComposition = document.getElementById('compositionChart').getContext('2d');
            const wasteData = data.waste_composition || {};

            const compData = wasteData.data && wasteData.data.length > 0
                ? wasteData.data
                : [1]; // Default filler if empty

            const compLabels = wasteData.labels && wasteData.labels.length > 0
                ? wasteData.labels
                : ['ไม่มีข้อมูล'];

            new Chart(ctxComposition, {
                type: 'doughnut',
                data: {
                    labels: compLabels,
                    datasets: [{
                        data: compData,
                        backgroundColor: [
                            '#2ecc71', '#3498db', '#f1c40f', '#e74c3c', '#95a5a6',
                            '#9b59b6', '#e67e22', '#1abc9c'
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8 }
                        }
                    },
                    cutout: '70%'
                }
            });
        })
        .catch(error => console.error('Error loading dashboard stats:', error));
});
