
(function () {
    "use strict";
    /*---------------------------------------------------------------------
        Fieldset
    -----------------------------------------------------------------------*/
    
    let currentTab = 0;

    const ActiveTab = (n) => {
        if (n == 0) {
            document.getElementById("infoPessoais").classList.add("active");
            document.getElementById("infoPessoais").classList.remove("done");

            document.getElementById("infoAdicionais").classList.remove("done");
            document.getElementById("infoAdicionais").classList.remove("active");
        }

        if (n == 1) {
            document.getElementById("infoPessoais").classList.add("done");

            document.getElementById("infoAdicionais").classList.add("active");
            document.getElementById("infoAdicionais").classList.remove("done");

            document.getElementById("contEndereco").classList.remove("active");
            document.getElementById("contEndereco").classList.remove("done");
            document.getElementById("compFamiliar").classList.remove("active");
            document.getElementById("compFamiliar").classList.remove("done");
            document.getElementById("confirm").classList.remove("active");
            document.getElementById("confirm").classList.remove("done");
        }

        if (n == 2) {
            document.getElementById("infoPessoais").classList.add("done");
            document.getElementById("infoAdicionais").classList.add("done");

            document.getElementById("contEndereco").classList.add("active");
            document.getElementById("contEndereco").classList.remove("done");

            document.getElementById("compFamiliar").classList.remove("active");
            document.getElementById("compFamiliar").classList.remove("done");
            document.getElementById("confirm").classList.remove("active");
            document.getElementById("confirm").classList.remove("done");
        }

        if (n == 3) {
            document.getElementById("infoPessoais").classList.add("done");
            document.getElementById("infoAdicionais").classList.add("done");
            document.getElementById("contEndereco").classList.add("done");

            document.getElementById("compFamiliar").classList.add("active");
            document.getElementById("compFamiliar").classList.remove("done");

            document.getElementById("confirm").classList.remove("active");
            document.getElementById("confirm").classList.remove("done");
        }

        if (n == 4) {
            document.getElementById("infoPessoais").classList.add("done");
            document.getElementById("infoAdicionais").classList.add("done");
            document.getElementById("contEndereco").classList.add("done");
            document.getElementById("compFamiliar").classList.add("done");

            document.getElementById("confirm").classList.add("active");
            document.getElementById("confirm").classList.remove("done");
        }
    };

    const showTab = (n) => {
        var x = document.getElementsByTagName("fieldset");
        // garante limites
        if (n < 0) n = 0;
        if (n > x.length - 1) n = x.length - 1;
        currentTab = n;

        // exibe o atual (o anterior é ocultado no nextBtnFunction)
        x[n].style.display = "block";
        ActiveTab(n);
    };

    const nextBtnFunction = (n) => {
        var x = document.getElementsByTagName("fieldset");

        // trava nos limites
        if ((currentTab + n) < 0 || (currentTab + n) > x.length - 1) return;

        x[currentTab].style.display = "none";
        currentTab = currentTab + n;
        showTab(currentTab);
    };

    // Inicializa
    showTab(0);

    const nextbtn = document.querySelectorAll('.next');
    Array.from(nextbtn, (nbtn) => {
        nbtn.addEventListener('click', function () {
            nextBtnFunction(1);
        });
    });

    const prebtn = document.querySelectorAll('.previous');
    Array.from(prebtn, (pbtn) => {
        pbtn.addEventListener('click', function () {
            nextBtnFunction(-1);
        });
    });

})();

