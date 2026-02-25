/**
 * OS Specialized Logic: Digital Signature and Photo Handling
 */
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('signature-pad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let painting = false;

        function startPosition(e) {
            painting = true;
            draw(e);
        }

        function endPosition() {
            painting = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!painting) return;

            const rect = canvas.getBoundingClientRect();
            const x = e.clientX ? e.clientX - rect.left : e.touches[0].clientX - rect.left;
            const y = e.clientY ? e.clientY - rect.top : e.touches[0].clientY - rect.top;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#2c3e50';

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        // Mouse events
        canvas.addEventListener('mousedown', startPosition);
        canvas.addEventListener('mouseup', endPosition);
        canvas.addEventListener('mousemove', draw);

        // Touch events
        canvas.addEventListener('touchstart', startPosition);
        canvas.addEventListener('touchend', endPosition);
        canvas.addEventListener('touchmove', function (e) {
            e.preventDefault();
            draw(e);
        });

        // Clear button
        document.getElementById('clear-signature').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });

        // Form submission
        document.getElementById('form-signature').addEventListener('submit', function (e) {
            const signature = canvas.toDataURL();
            document.getElementById('input-signature').value = signature;
        });
    }
});
