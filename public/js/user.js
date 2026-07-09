const roles = document.querySelectorAll('input[name="role"]');
const libelle = document.getElementById('libelle-container');


function toggleLibelle() {

    const selected = document.querySelector(
        'input[name="role"]:checked'
    );


    if (selected.value === "ROLE_FOURNISSEUR") {
        libelle.style.display = "block";
    } else {
        libelle.style.display = "none";
    }

}


roles.forEach(role => {
    role.addEventListener('change', toggleLibelle);
});


toggleLibelle();