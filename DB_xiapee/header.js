export function Header() {
    const header_cont = document.createElement('div')
    const left = document.createElement('div')
    const middle = document.createElement('div')
    const right = document.createElement('div')
    const logo_img = document.createElement('a')
    const name = document.createElement('p')
    const home_icon = document.createElement('a')
    const cart_icon = document.createElement('a')
    const tracking_icon = document.createElement('a')
    const money_icon = document.createElement('a')
    const profile = document.createElement('a')
    const search_inp = document.createElement('button')
    // const lens = document.createElement('img')

    logo_img.href = "/dashboard"
    name.innerHTML = "XIApee"
    home_icon.href = "/pages/login/index.html"
    cart_icon.href = "/dashboard"
    tracking_icon.href = "/dashboard"
    money_icon.href = "/dashboard"
    profile.href = "/pages/profile/index.html"
    search_inp.placeholder = "Search product here"
    search_inp.type = "text"
    search_inp.innerHTML = "Log out"
    // lens.src = "/public/icons/lens_icon.svg"
    logo_img.style.backgroundImage = `url(/public/img/logo.png)`
    home_icon.style.backgroundImage = `url(/public/icons/home_icon.png)`
    cart_icon.style.backgroundImage = `url(/public/icons/cart_icon.png)`
    tracking_icon.style.backgroundImage = `url(/public/icons/tracking_icon.png)`
    money_icon.style.backgroundImage = `url(/public/icons/money_icon.png)`
    profile.style.backgroundImage = `url(/public/icons/profile_icon.svg)`

    header_cont.classList.add('header_cont')
    left.classList.add('left')
    middle.classList.add('middle')
    right.classList.add('right')
    logo_img.classList.add('logo_img')
    home_icon.classList.add('home_icon')
    cart_icon.classList.add('cart_icon')
    tracking_icon.classList.add('tracking_icon')
    money_icon.classList.add('money_icon')
    profile.classList.add('profile')
    search_inp.classList.add('search_inp')
    // lens.classList.add('lens')
    home_icon.classList.add('icon')
    cart_icon.classList.add('icon')
    tracking_icon.classList.add('icon')
    money_icon.classList.add('icon')
    profile.classList.add('icon')

    header_cont.append(left, middle, right)
    left.append(logo_img, name)
    middle.append(home_icon, cart_icon, tracking_icon, money_icon, profile)
    right.append(search_inp)

    const header = document.querySelector('header')
    header.append(header_cont)
}