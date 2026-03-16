document.addEventListener('DOMContentLoaded', function () {
    const fotoInput = document.getElementById('fotoInput');
    const preview = document.getElementById('previewFoto');

    if (fotoInput && preview) {
        fotoInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (evt) {
                preview.src = evt.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    document.querySelectorAll('.btn-excluir').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('Deseja realmente excluir este cadastro?')) {
                e.preventDefault();
            }
        });
    });
});
