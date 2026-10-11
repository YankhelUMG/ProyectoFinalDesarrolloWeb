const api = "http://localhost/e-proyecto/api/admin/Users.php";
async function getUsers() {
  let $ = (doc) => document.querySelector(doc);
  try {
    const response = await fetch(api);
    const data = await response.json();
    data.data.forEach((user) => {
        $("tbody").innerHTML += <tr>
            <td>${user.codigo}</td>
            <td>${user.usuario}</td>
            <td>${user.nombre}</td>
            <td>${user.estado}</td>
        </tr>;
    });
  } catch (error) {
    console.log(error);
  }
}
getUsers();
const form = document.querySelector("form");
form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const datos = new FormData(form);
    try{
        const response = await fetch(api, {
            method: "POST",
            body: datos
        });
        const result = await response.json();
        alert(result.message);
        form.reset();
    } catch (error) {
        console.log(error);
    }
})
