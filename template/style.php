<?php declare(strict_types=1);
/**
 * This file is part of the prooph/event-machine-docs.
 * (c) 2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
?>
<style>
    body {
        font-size: 16px;
    }
    /* Header Section */
    header {
        font-size: 17px;
        font-weight: 400;
    }

    a {
        color: #04A1B0;
    }

    a:hover, a:active {
        color: #07BDF1;
        text-decoration: none;
    }

    .dropdown-menu {
        font-size: 17px;
    }

    .prooph-logo {
        float: left;
        margin-left: 8px;
        margin-right: 8px;
        transition-timing-function: ease-in-out;
        transition: all 5s;
        height: 50px;
        padding: 5px;
    }

    .jumbotron {
       background-color: #04A1B0;
    }

    .jumbotron .display-1 {
        color: white;
    }

    .jumbotron .display-2 {
        color: white;
    }

    .jumbotron pre {
        display: block;
        color: #f8f8f2;
        font-size: 13px;
        margin-top: 60px;
        border-radius: 0;
        border: none;
        background: #272822;
        padding: 1em;
        padding-bottom: 0px;
        overflow: auto;
        text-shadow: 0 1px rgba(0,0,0,0.3);
        font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
        text-align: left;
        white-space: pre;
        word-spacing: normal;
        word-break: normal;
        word-wrap: normal;
        line-height: 1.5;
        -moz-tab-size: 4;
        -o-tab-size: 4;
        tab-size: 4;
        -webkit-hyphens: none;
        -moz-hyphens: none;
        -ms-hyphens: none;
        hyphens: none;
    }

    .jumbotron pre.line-numbers {
        position: relative;
        padding-left: 3.8em;
        counter-reset: linenumber;
    }

    .jumbotron pre code {
        color: white;
        margin-left: -80px;
    }


    h2.front {
        text-align: center;
        font-size: 56px;
        margin-top: 100px;
        padding: 20px;
    }

    .intro {
        margin-top: 30px;
        font-size: 24px;
        font-weight: 200;
    }

    .intro.sub-intro {
        margin-top: 15px;
        margin-bottom: 15px;
    }

    .flavour {
        height: 200px;
    }

    .flavour h3 {
        color: white;
        font-size: 30px;
    }

    .flavour h3 small {
        color: #f2f2f2;
        font-size: 20px;
    }

    .flavour.prototyping {
        background-color: #CC3340;
    }

    .flavour.functional {
        background-color: #EB6842;
    }

    .flavour.oop {
        background-color: #715671;
    }

    .flavour.mixed {
        background-color: #26896C;
    }

    .btn-get-started {
        background-color: #04A1B0;
        color: white;
        padding: 20px 26px;
        font-size: 35px;
        border-radius: 8px;
        width: 100%;
        -webkit-transition-duration: 0.4s; /* Safari */
        transition-duration: 0.4s;
    }

    .btn-get-started:hover {
        background-color: #04c8d7;
        color: white;
        margin-top: 0px;
    }

    .img-center {
        display: block;
        margin-left: auto;
        margin-right: auto;
        width: 50%;
    }


    #forkongithub a {
        background: #000;
        color: #fff;
        text-decoration: none;
        font-family: arial, sans-serif;
        text-align: center;
        font-weight: bold;
        padding: 5px 40px;
        font-size: 1rem;
        line-height: 2rem;
        position: relative;
        transition: 0.5s;
    }

    #forkongithub a:hover {
        background: #04c8d7;
        color: #fff;
    }

    #forkongithub a::before, #forkongithub a::after {
        content: "";
        width: 100%;
        display: block;
        position: absolute;
        top: 1px;
        left: 0;
        height: 1px;
        background: #fff;
    }

    #forkongithub a::after {
        bottom: 1px;
        top: auto;
    }

    .first-row {
        margin-top: 30px;
    }

    @media screen and (min-width: 800px) {
        #forkongithub {
            position: fixed;
            display: block;
            top: 0;
            right: 0;
            width: 200px;
            overflow: hidden;
            height: 200px;
            z-index: 1000;
        }

        #forkongithub a {
            width: 200px;
            position: absolute;
            top: 85px;
            right: -60px;
            transform: rotate(45deg);
            -webkit-transform: rotate(45deg);
            -ms-transform: rotate(45deg);
            -moz-transform: rotate(45deg);
            -o-transform: rotate(45deg);
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.8);
        }
    }

    @media screen and (max-width: 799px) {
        .jumbotron {
            margin-top: 60px;
        }

        .first-row {
            margin-top: 80px;
        }
    }

    @media screen and (max-width: 991px) {
        .jumbotron pre {
            display: none;
        }

        .flavour {
            margin-top: 60px;
        }
    }

    .navbar-default {
        background-color: rgb(0,63,105);
    }

    .navbar-front {
        background-color: rgb(243, 243, 243);
    }

    .navbar-default .navbar-nav>li>a:hover {
        color: #04A1B0;
        text-decoration: none;
    }



    .alert {
        border-width: 0 0 0 4px;
        background-color: transparent;
        color: inherit;
    }

    .alert-light {
        border-color: #715671;
        color: #818182;
        background-color: #fefefe;
    }

    .alert-dark {
        color: #1b1e21;
        background-color: #d6d8d9;
        border-color: #c6c8ca;
    }

    .alert-info {
        border-color: #04A1B0;
    }

    .alert-warning {
        border-color: #EB6842;
    }

    .alert-danger {
        border-color: #CC3340;
    }

    .alert-success {
        border-color: #26896C;
    }

    .alert .alert-link {
        color: #04A1B0;
        text-decoration: none;
    }

    .alert .alert-link:hover, .alert .alert-link:active {
        color: #07BDF1;
        text-decoration: none;
    }

    .alert.alert-important {
        border-width: 0 1px 4px 1px;
        background-color: #CC3340;
        color: #fff;
    }

    .alert.alert-important .alert-link {
        color: #ffffff;
        text-decoration: underline;
    }

    .alert.alert-important .alert-link:hover, .alert .alert-link:active {
        color: #ee374b;
        text-decoration: underline;
    }
</style>
