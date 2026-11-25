@extends('errors.layout')
@section('code', '404')
@section('title', 'Такой страницы нет')
@section('message')
  Адрес <code>{{ request()->path() === '/' ? '/' : '/' . request()->path() }}</code> не найден.
  Возможно, ссылка устарела или в ней опечатка.
@endsection
@section('hint', 'Для касс: проверьте адрес метода API, например /api/v1/bonus/balance/{short_code}')
